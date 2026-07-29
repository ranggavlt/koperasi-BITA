<?php

namespace App\Services;

use App\Models\Anggota;
use App\Models\Akun;
use App\Models\DompetKoperasi;
use App\Models\JenisSimpanan;
use App\Models\JurnalUmum;
use App\Models\Karyawan;
use App\Models\MutasiKas;
use App\Models\ReversalTransaksi;
use App\Models\SaldoSimpananSukarela;
use App\Models\SiklusKeanggotaan;
use App\Models\Simpanan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SimpananSukarelaService
{
    public function __construct(
        private readonly MutasiKasService $mutasiKasService,
        private readonly AkuntansiService $akuntansiService
    ) {
    }

    public function create(array $data, ?int $userId = null): Simpanan
    {
        $jenisTransaksi = $data['jenis_transaksi'] ?? Simpanan::JENIS_SETORAN;

        return match ($jenisTransaksi) {
            Simpanan::JENIS_SETORAN => $this->setoran($data, $userId),
            Simpanan::JENIS_PENARIKAN => $this->penarikan($data, $userId),
            default => throw ValidationException::withMessages([
                'jenis_transaksi' => 'Jenis transaksi Simpanan Sukarela tidak valid.',
            ]),
        };
    }

    public function setoran(array $data, ?int $userId = null): Simpanan
    {
        return $this->post(array_merge($data, ['jenis_transaksi' => Simpanan::JENIS_SETORAN]), $userId);
    }

    public function penarikan(array $data, ?int $userId = null): Simpanan
    {
        return $this->post(array_merge($data, ['jenis_transaksi' => Simpanan::JENIS_PENARIKAN]), $userId);
    }

    public function koreksi(Simpanan $simpanan, string $alasan, int $userId): ReversalTransaksi
    {
        $this->assertReason($alasan);

        try {
            return DB::transaction(function () use ($simpanan, $alasan, $userId): ReversalTransaksi {
                /** @var Simpanan $locked */
                $locked = Simpanan::query()
                    ->with(['anggota.karyawan', 'jenisSimpanan.akun', 'dompet.akun', 'mutasiKas', 'jurnal'])
                    ->lockForUpdate()
                    ->findOrFail($simpanan->id);

                if (! $locked->isSimpananSukarela()) {
                    throw ValidationException::withMessages([
                        'simpanan' => 'Koreksi Transaksi hanya tersedia untuk Simpanan Sukarela.',
                    ]);
                }

                if ($locked->status !== Simpanan::STATUS_SETTLED || $locked->reversal_transaksi_id) {
                    throw ValidationException::withMessages([
                        'simpanan' => 'Transaksi ini sudah dikoreksi atau belum berstatus posted.',
                    ]);
                }

                if (! in_array($locked->jenis_transaksi, [Simpanan::JENIS_SETORAN, Simpanan::JENIS_PENARIKAN], true)) {
                    throw ValidationException::withMessages([
                        'simpanan' => 'Jenis transaksi Sukarela lama belum terklasifikasi; lakukan rekonsiliasi manual terlebih dahulu.',
                    ]);
                }

                if (ReversalTransaksi::query()
                    ->where('source_type', Simpanan::class)
                    ->where('source_id', $locked->id)
                    ->lockForUpdate()
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'simpanan' => 'Transaksi ini sudah mempunyai koreksi.',
                    ]);
                }

                $anggota = $locked->anggota;
                $siklusId = (int) $locked->siklus_keanggotaan_id;
                $jenisId = (int) $locked->jenis_simpanan_id;
                $dompet = DompetKoperasi::query()
                    ->with('akun')
                    ->lockForUpdate()
                    ->findOrFail((int) $locked->dompet_id);
                $saldo = $this->saldoRow($anggota, $siklusId, $jenisId, true);
                $nominalCents = $this->decimalToCents($locked->jumlah);
                $saldoCents = $this->decimalToCents($saldo->saldo);

                if ($locked->jenis_transaksi === Simpanan::JENIS_SETORAN) {
                    if ($saldoCents < $nominalCents) {
                        throw ValidationException::withMessages([
                            'simpanan' => 'Saldo Simpanan Sukarela tidak cukup untuk mengoreksi setoran ini.',
                        ]);
                    }

                    $this->assertDompetSaldo($dompet, $nominalCents, 'Saldo Dompet tidak cukup untuk koreksi setoran.');
                    $saldoSesudah = $saldoCents - $nominalCents;
                    $mutasiTipe = 'keluar';
                } else {
                    $saldoSesudah = $saldoCents + $nominalCents;
                    $mutasiTipe = 'masuk';
                }

                $reversal = ReversalTransaksi::query()->create([
                    'kode_reversal' => $this->nextCode('reversal', 'REV'),
                    'source_type' => Simpanan::class,
                    'source_id' => $locked->id,
                    'jenis_reversal' => ReversalTransaksi::JENIS_SIMPANAN_SUKARELA_CORRECTION,
                    'nominal' => $this->decimalFromCents($nominalCents),
                    'alasan' => trim($alasan),
                    'status' => ReversalTransaksi::STATUS_PROCESSED,
                    'original_jurnal_id' => $locked->jurnal?->id,
                    'original_mutasi_id' => $locked->mutasiKas?->id,
                    'dompet_refund_id' => $dompet->id,
                    'created_by' => $userId,
                    'processed_by' => $userId,
                    'processed_at' => $this->now(),
                    'idempotency_key' => 'reversal:simpanan-sukarela:' . $locked->id,
                ]);

                $mutasi = $this->mutasiKasService->record([
                    'idempotency_key' => 'reversal:simpanan-sukarela:mutasi:' . $reversal->id,
                    'dompet_id' => $dompet->id,
                    'tipe' => $mutasiTipe,
                    'jumlah' => intdiv($nominalCents, 100),
                    'keterangan' => 'Koreksi Transaksi Simpanan Sukarela ' . ($locked->kode_transaksi ?: ('#' . $locked->id)),
                    'referensi_tipe' => ReversalTransaksi::class,
                    'referensi_id' => $reversal->id,
                    'tanggal' => $this->today(),
                ]);

                $saldo->update(['saldo' => $this->decimalFromCents($saldoSesudah)]);
                $locked->update([
                    'status' => Simpanan::STATUS_REVERSED,
                    'reversal_transaksi_id' => $reversal->id,
                ]);

                $jurnal = $this->akuntansiService->recordSimpananSukarelaCorrection(
                    $reversal->fresh(),
                    $locked->fresh(['jenisSimpanan.akun']),
                    $dompet->akun,
                    $userId
                );

                $this->assertPostingBalanced($jurnal);
                $this->assertReversalMutasi($mutasi, $dompet, $mutasiTipe);

                return $reversal->fresh(['originalJurnal', 'originalMutasi', 'dompetRefund']);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'simpanan' => 'Transaksi ini sudah mempunyai koreksi. Muat ulang halaman untuk melihat status terbaru.',
            ]);
        }
    }

    public function saldoTersedia(Anggota $anggota, ?int $jenisSimpananId = null): int
    {
        $jenis = $jenisSimpananId
            ? $this->activeSukarelaJenis($jenisSimpananId)
            : $this->activeSukarelaJenis();
        $siklus = $anggota->siklusAktif()->first();

        if (! $siklus) {
            return 0;
        }

        $saldo = SaldoSimpananSukarela::query()
            ->where('anggota_id', $anggota->id)
            ->where('siklus_keanggotaan_id', $siklus->id)
            ->where('jenis_simpanan_id', $jenis->id)
            ->value('saldo');

        return intdiv($this->decimalToCents($saldo ?? '0.00'), 100);
    }

    public function recalculateSaldoCents(int $anggotaId, int $siklusId, int $jenisSimpananId): int
    {
        return Simpanan::query()
            ->where('anggota_id', $anggotaId)
            ->where('siklus_keanggotaan_id', $siklusId)
            ->where('jenis_simpanan_id', $jenisSimpananId)
            ->where('status', Simpanan::STATUS_SETTLED)
            ->whereIn('jenis_transaksi', [Simpanan::JENIS_SETORAN, Simpanan::JENIS_PENARIKAN])
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get()
            ->reduce(function (int $saldo, Simpanan $simpanan): int {
                $nominal = $this->decimalToCents($simpanan->jumlah);

                return $simpanan->jenis_transaksi === Simpanan::JENIS_SETORAN
                    ? $saldo + $nominal
                    : $saldo - $nominal;
            }, 0);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{setoran:int, penarikan:int, saldo_aktif:int, dikoreksi:int}
     */
    public function summary(array $filters = []): array
    {
        $base = $this->simpananSukarelaQuery($filters);

        $setoran = (clone $base)
            ->where('jenis_transaksi', Simpanan::JENIS_SETORAN)
            ->where('status', Simpanan::STATUS_SETTLED)
            ->sum('jumlah');
        $penarikan = (clone $base)
            ->where('jenis_transaksi', Simpanan::JENIS_PENARIKAN)
            ->where('status', Simpanan::STATUS_SETTLED)
            ->sum('jumlah');

        $saldoAktif = SaldoSimpananSukarela::query()
            ->whereHas('anggota', fn ($query) => $query->where('status', Anggota::STATUS_AKTIF))
            ->whereHas('siklusKeanggotaan', fn ($query) => $query->where('status', SiklusKeanggotaan::STATUS_ACTIVE))
            ->sum('saldo');

        $dikoreksi = (clone $base)
            ->where('status', Simpanan::STATUS_REVERSED)
            ->count();

        return [
            'setoran' => $this->rupiahInt($setoran),
            'penarikan' => $this->rupiahInt($penarikan),
            'saldo_aktif' => $this->rupiahInt($saldoAktif),
            'dikoreksi' => $dikoreksi,
        ];
    }

    private function post(array $data, ?int $userId = null): Simpanan
    {
        $idempotencyKey = $this->normalizeIdempotencyKey($data['idempotency_key'] ?? null);

        try {
            return DB::transaction(function () use ($data, $userId, $idempotencyKey): Simpanan {
                $existing = Simpanan::query()
                    ->with(['anggota.karyawan', 'jenisSimpanan.akun', 'dompet.akun', 'mutasiKas.dompet.akun', 'jurnal.details.akun'])
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $existing;
                }

                $tanggal = $this->normalizeTanggal($data['tanggal'] ?? $this->today());
                $jenisTransaksi = $data['jenis_transaksi'] ?? Simpanan::JENIS_SETORAN;
                if (! in_array($jenisTransaksi, [Simpanan::JENIS_SETORAN, Simpanan::JENIS_PENARIKAN], true)) {
                    throw ValidationException::withMessages([
                        'jenis_transaksi' => 'Jenis transaksi Simpanan Sukarela tidak valid.',
                    ]);
                }

                $metode = $data['metode_pembayaran'] ?? Simpanan::METODE_TUNAI;
                if (! in_array($metode, [Simpanan::METODE_TUNAI, Simpanan::METODE_TRANSFER_BANK], true)) {
                    throw ValidationException::withMessages([
                        'metode_pembayaran' => 'Simpanan Sukarela hanya boleh memakai Tunai atau Transfer Bank.',
                    ]);
                }

                $jumlah = $this->rupiahInt($data['jumlah'] ?? 0);
                if ($jumlah <= 0) {
                    throw ValidationException::withMessages([
                        'jumlah' => 'Nominal Simpanan Sukarela wajib lebih besar dari nol.',
                    ]);
                }
                $jumlahCents = $jumlah * 100;

                $anggota = Anggota::query()
                    ->with('karyawan')
                    ->lockForUpdate()
                    ->findOrFail((int) $data['anggota_id']);
                $siklus = $this->assertActiveAnggotaAndCycle($anggota);
                $jenis = isset($data['jenis_simpanan_id'])
                    ? $this->activeSukarelaJenis((int) $data['jenis_simpanan_id'])
                    : $this->activeSukarelaJenis();
                $dompet = DompetKoperasi::query()
                    ->with('akun')
                    ->lockForUpdate()
                    ->findOrFail((int) $data['dompet_id']);

                $this->assertAkunSimpanan($jenis->akun);
                $this->assertDompetAkun($dompet);
                $this->assertMetodeMatchesDompet($metode, $dompet);

                $saldo = $this->saldoRow($anggota, $siklus->id, $jenis->id, true);
                $saldoSebelumCents = $this->decimalToCents($saldo->saldo);

                if ($jenisTransaksi === Simpanan::JENIS_PENARIKAN) {
                    if ($jumlahCents > $saldoSebelumCents) {
                        throw ValidationException::withMessages([
                            'jumlah' => 'Nominal penarikan tidak boleh melebihi saldo Simpanan Sukarela.',
                        ]);
                    }

                    $this->assertDompetSaldo($dompet, $jumlahCents, 'Saldo Dompet tidak mencukupi untuk penarikan.');
                    $saldoSesudahCents = $saldoSebelumCents - $jumlahCents;
                    $mutasiTipe = 'keluar';
                } else {
                    $saldoSesudahCents = $saldoSebelumCents + $jumlahCents;
                    $mutasiTipe = 'masuk';
                }

                $simpanan = Simpanan::query()->create([
                    'idempotency_key' => $idempotencyKey,
                    'kode_transaksi' => $this->nextCode('simpanan_sukarela', 'SSK', $tanggal),
                    'anggota_id' => $anggota->id,
                    'karyawan_id' => $anggota->karyawan_id,
                    'siklus_keanggotaan_id' => $siklus->id,
                    'jenis_simpanan_id' => $jenis->id,
                    'kode_jenis_snapshot' => $jenis->kode,
                    'nama_jenis_snapshot' => $jenis->nama_jenis,
                    'nominal_snapshot' => $this->decimalFromCents($jumlahCents),
                    'jumlah' => $this->decimalFromCents($jumlahCents),
                    'jenis_transaksi' => $jenisTransaksi,
                    'dompet_id' => $dompet->id,
                    'saldo_sebelum_snapshot' => $this->decimalFromCents($saldoSebelumCents),
                    'saldo_sesudah_snapshot' => $this->decimalFromCents($saldoSesudahCents),
                    'nomor_referensi' => $data['nomor_referensi'] ?? null,
                    'metode_pembayaran' => $metode,
                    'status' => Simpanan::STATUS_SETTLED,
                    'settled_at' => $this->now(),
                    'tanggal' => $tanggal->toDateString(),
                    'keterangan' => $data['keterangan'] ?? null,
                    'created_by' => $userId,
                ]);

                $mutasi = $this->mutasiKasService->record([
                    'idempotency_key' => $this->mutasiIdempotencyKey($simpanan, $idempotencyKey),
                    'dompet_id' => $dompet->id,
                    'tipe' => $mutasiTipe,
                    'jumlah' => $jumlah,
                    'keterangan' => $jenisTransaksi === Simpanan::JENIS_SETORAN
                        ? 'Setoran Simpanan Sukarela'
                        : 'Penarikan Simpanan Sukarela',
                    'referensi_tipe' => Simpanan::class,
                    'referensi_id' => $simpanan->id,
                    'tanggal' => $tanggal->toDateString(),
                ]);

                $jurnal = $jenisTransaksi === Simpanan::JENIS_SETORAN
                    ? $this->akuntansiService->recordSimpanan(
                        $simpanan->fresh('jenisSimpanan.akun'),
                        $dompet->akun,
                        $userId,
                        $this->jurnalIdempotencyKey($simpanan, $idempotencyKey)
                    )
                    : $this->akuntansiService->recordSimpananSukarelaPenarikan(
                        $simpanan->fresh('jenisSimpanan.akun'),
                        $dompet->akun,
                        $userId,
                        $this->jurnalIdempotencyKey($simpanan, $idempotencyKey)
                    );

                $saldo->update(['saldo' => $this->decimalFromCents($saldoSesudahCents)]);
                $this->assertPostingBalanced($jurnal);
                $this->assertReversalMutasi($mutasi, $dompet, $mutasiTipe);

                return $simpanan->fresh(['anggota.karyawan', 'jenisSimpanan.akun', 'dompet.akun', 'mutasiKas.dompet.akun', 'jurnal.details.akun']);
            });
        } catch (UniqueConstraintViolationException) {
            $existing = Simpanan::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing->fresh(['anggota.karyawan', 'jenisSimpanan.akun', 'dompet.akun', 'mutasiKas.dompet.akun', 'jurnal.details.akun']);
            }

            throw ValidationException::withMessages([
                'simpanan' => 'Transaksi Simpanan Sukarela gagal karena kode atau idempotency bertabrakan. Muat ulang form lalu coba lagi.',
            ]);
        }
    }

    private function assertActiveAnggotaAndCycle(Anggota $anggota): SiklusKeanggotaan
    {
        if ($anggota->status !== Anggota::STATUS_AKTIF || $anggota->karyawan?->status_kerja !== Karyawan::STATUS_AKTIF) {
            throw ValidationException::withMessages([
                'anggota_id' => 'Simpanan Sukarela hanya dapat diproses untuk Anggota aktif dengan Karyawan aktif.',
            ]);
        }

        $siklus = SiklusKeanggotaan::query()
            ->where('anggota_id', $anggota->id)
            ->where('status', SiklusKeanggotaan::STATUS_ACTIVE)
            ->lockForUpdate()
            ->first();

        if (! $siklus) {
            throw ValidationException::withMessages([
                'anggota_id' => 'Anggota ini tidak memiliki siklus keanggotaan aktif. Gunakan proses Penyelesaian Keanggotaan untuk saldo lama.',
            ]);
        }

        return $siklus;
    }

    private function activeSukarelaJenis(?int $jenisSimpananId = null): JenisSimpanan
    {
        $query = JenisSimpanan::query()
            ->with('akun')
            ->aktif()
            ->where('kode', JenisSimpanan::KODE_SIMPANAN_MANASUKA)
            ->where('kategori', JenisSimpanan::KATEGORI_MANASUKA);

        if ($jenisSimpananId) {
            $query->where('id', $jenisSimpananId);
        }

        $jenis = $query->lockForUpdate()->first();

        if (! $jenis) {
            throw ValidationException::withMessages([
                'jenis_simpanan_id' => 'Master Simpanan Manasuka aktif belum dikonfigurasi.',
            ]);
        }

        return $jenis;
    }

    private function saldoRow(Anggota $anggota, int $siklusId, int $jenisId, bool $lock = false): SaldoSimpananSukarela
    {
        $query = SaldoSimpananSukarela::query()
            ->where('anggota_id', $anggota->id)
            ->where('siklus_keanggotaan_id', $siklusId)
            ->where('jenis_simpanan_id', $jenisId);

        if ($lock) {
            $query->lockForUpdate();
        }

        $saldo = $query->first();

        if ($saldo) {
            return $saldo;
        }

        try {
            SaldoSimpananSukarela::query()->create([
                'anggota_id' => $anggota->id,
                'siklus_keanggotaan_id' => $siklusId,
                'jenis_simpanan_id' => $jenisId,
                'saldo' => '0.00',
            ]);
        } catch (QueryException) {
        }

        $query = SaldoSimpananSukarela::query()
            ->where('anggota_id', $anggota->id)
            ->where('siklus_keanggotaan_id', $siklusId)
            ->where('jenis_simpanan_id', $jenisId);

        if ($lock) {
            $query->lockForUpdate();
        }

        $saldo = $query->first();

        if (! $saldo) {
            throw new RuntimeException('Saldo Simpanan Sukarela tidak dapat dibuat.');
        }

        return $saldo;
    }

    private function assertAkunSimpanan(?Akun $akun): void
    {
        if (! $akun || ! $akun->is_aktif || ! in_array($akun->kategori, ['kewajiban', 'ekuitas'], true) || $akun->posisi_saldo !== 'kredit') {
            throw ValidationException::withMessages([
                'jenis_simpanan_id' => 'COA Simpanan Sukarela wajib aktif, kategori Kewajiban/Ekuitas, dan saldo normal Kredit.',
            ]);
        }
    }

    private function assertDompetAkun(DompetKoperasi $dompet): void
    {
        $akun = $dompet->akun;

        if (! $akun || ! $akun->is_aktif || $akun->kategori !== 'aset' || $akun->posisi_saldo !== 'debit') {
            throw ValidationException::withMessages([
                'dompet_id' => 'Dompet wajib memiliki COA aktif kategori Aset dengan saldo normal Debit.',
            ]);
        }
    }

    private function assertMetodeMatchesDompet(string $metode, DompetKoperasi $dompet): void
    {
        if ($metode === Simpanan::METODE_TUNAI && $dompet->jenis_dompet !== DompetKoperasi::JENIS_KAS) {
            throw ValidationException::withMessages([
                'dompet_id' => 'Metode Tunai wajib memakai Dompet Kas.',
            ]);
        }

        if ($metode === Simpanan::METODE_TRANSFER_BANK && $dompet->jenis_dompet !== DompetKoperasi::JENIS_BANK) {
            throw ValidationException::withMessages([
                'dompet_id' => 'Metode Transfer Bank wajib memakai Dompet Bank.',
            ]);
        }
    }

    private function assertDompetSaldo(DompetKoperasi $dompet, int $nominalCents, string $message): void
    {
        if ($this->decimalToCents($dompet->saldo) < $nominalCents) {
            throw ValidationException::withMessages([
                'dompet_id' => $message,
            ]);
        }
    }

    private function assertReason(string $alasan): void
    {
        if (mb_strlen(trim($alasan)) < 5) {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan Koreksi wajib diisi minimal 5 karakter.',
            ]);
        }
    }

    private function assertPostingBalanced(JurnalUmum $jurnal): void
    {
        $jurnal->loadMissing('details');
        $debit = $jurnal->details->sum(fn ($detail) => (float) $detail->debit);
        $kredit = $jurnal->details->sum(fn ($detail) => (float) $detail->kredit);

        if (abs($debit - $kredit) > 0.01) {
            throw new RuntimeException('Jurnal Simpanan Sukarela tidak berimbang.');
        }
    }

    private function assertReversalMutasi(MutasiKas $mutasi, DompetKoperasi $dompet, string $tipe): void
    {
        if ((int) $mutasi->dompet_id !== (int) $dompet->id || $mutasi->tipe !== $tipe) {
            throw new RuntimeException('Mutasi Kas Simpanan Sukarela tidak konsisten dengan transaksi.');
        }
    }

    private function nextCode(string $jenis, string $prefix, CarbonInterface|string|null $tanggal = null): string
    {
        $periode = $tanggal
            ? $this->normalizeTanggal($tanggal)->format('Ym')
            : CarbonImmutable::now($this->timezone())->format('Ym');

        try {
            DB::table('nomor_urut_transaksi')->insert([
                'jenis' => $jenis,
                'periode' => $periode,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException) {
        }

        $counter = DB::table('nomor_urut_transaksi')
            ->where('jenis', $jenis)
            ->where('periode', $periode)
            ->lockForUpdate()
            ->first();

        if (! $counter) {
            throw new RuntimeException('Counter nomor transaksi Simpanan Sukarela tidak dapat dibuat.');
        }

        $next = ((int) $counter->last_number) + 1;

        DB::table('nomor_urut_transaksi')
            ->where('id', $counter->id)
            ->update([
                'last_number' => $next,
                'updated_at' => now(),
            ]);

        return sprintf('%s-%s-%06d', $prefix, $periode, $next);
    }

    private function mutasiIdempotencyKey(Simpanan $simpanan, string $idempotencyKey): string
    {
        if ($simpanan->jenis_transaksi === Simpanan::JENIS_SETORAN) {
            return 'simpanan:manual:mutasi:' . $idempotencyKey;
        }

        return 'simpanan-sukarela:penarikan:mutasi:' . $idempotencyKey;
    }

    private function jurnalIdempotencyKey(Simpanan $simpanan, string $idempotencyKey): string
    {
        if ($simpanan->jenis_transaksi === Simpanan::JENIS_SETORAN) {
            return 'simpanan:manual:jurnal:' . $idempotencyKey;
        }

        return 'simpanan-sukarela:penarikan:jurnal:' . $idempotencyKey;
    }

    private function simpananSukarelaQuery(array $filters = [])
    {
        return Simpanan::query()
            ->whereHas('jenisSimpanan', fn ($query) => $query
                ->where('kode', JenisSimpanan::KODE_SIMPANAN_MANASUKA)
                ->orWhere('kategori', JenisSimpanan::KATEGORI_MANASUKA))
            ->when($filters['anggota_id'] ?? null, fn ($query, $anggotaId) => $query->where('anggota_id', $anggotaId))
            ->when($filters['jenis_transaksi'] ?? null, fn ($query, $jenis) => $query->where('jenis_transaksi', $jenis))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['metode_pembayaran'] ?? null, fn ($query, $metode) => $query->where('metode_pembayaran', $metode))
            ->when($filters['tanggal_mulai'] ?? null, fn ($query, $date) => $query->whereDate('tanggal', '>=', $date))
            ->when($filters['tanggal_selesai'] ?? null, fn ($query, $date) => $query->whereDate('tanggal', '<=', $date));
    }

    private function normalizeIdempotencyKey(?string $key): string
    {
        $normalized = trim((string) $key);

        return $normalized !== '' ? $normalized : 'simpanan-sukarela:' . (string) Str::uuid();
    }

    private function normalizeTanggal(CarbonInterface|string $tanggal): CarbonImmutable
    {
        if ($tanggal instanceof CarbonInterface) {
            return CarbonImmutable::instance($tanggal)->setTimezone($this->timezone())->startOfDay();
        }

        return CarbonImmutable::parse((string) $tanggal, $this->timezone())->setTimezone($this->timezone())->startOfDay();
    }

    private function now()
    {
        return now($this->timezone());
    }

    private function today(): string
    {
        return CarbonImmutable::now($this->timezone())->toDateString();
    }

    private function timezone(): string
    {
        return (string) config('app.timezone', 'Asia/Jakarta');
    }

    private function rupiahInt(mixed $value): int
    {
        $cents = $this->decimalToCents($value);

        if ($cents % 100 !== 0) {
            throw ValidationException::withMessages([
                'jumlah' => 'Nominal harus berupa Rupiah bulat tanpa pecahan sen.',
            ]);
        }

        return intdiv($cents, 100);
    }

    private function decimalToCents(mixed $value): int
    {
        $normalized = trim((string) $value);
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $whole = preg_replace('/\D/', '', $whole) ?: '0';
        $fraction = substr(str_pad(preg_replace('/\D/', '', $fraction) ?? '', 2, '0'), 0, 2);
        $cents = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -1 * $cents : $cents;
    }

    private function decimalFromCents(int $cents): string
    {
        $negative = $cents < 0;
        $absolute = abs($cents);
        $value = intdiv($absolute, 100) . '.' . str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);

        return $negative ? '-' . $value : $value;
    }
}
