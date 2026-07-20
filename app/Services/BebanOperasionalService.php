<?php

namespace App\Services;

use App\Models\Akun;
use App\Models\BebanOperasional;
use App\Models\BebanOperasionalDetail;
use App\Models\DompetKoperasi;
use App\Models\JurnalUmum;
use App\Models\MutasiKas;
use App\Models\ReversalTransaksi;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BebanOperasionalService
{
    public function __construct(private readonly AkuntansiService $akuntansiService)
    {
    }

    public function createDraft(array $data, int $financeUserId): BebanOperasional
    {
        return DB::transaction(function () use ($data, $financeUserId): BebanOperasional {
            $tanggal = $this->normalizeDate($data['tanggal_beban']);
            $detailRows = $this->buildDraftDetailRows($data);
            $total = $this->sumDetails($detailRows);
            $dompet = $this->resolveDraftDompet((int) ($data['dompet_id'] ?? 0));
            $metode = $this->metodeFromDompet($dompet);

            $beban = BebanOperasional::query()->create([
                'kode_beban' => $this->nextCode('beban_operasional', 'BOP', $tanggal),
                'tanggal_beban' => $tanggal->toDateString(),
                'dompet_id' => $dompet->id,
                'metode_pembayaran' => $metode,
                'total_beban' => $this->rupiahDecimal($total),
                'status' => BebanOperasional::STATUS_DRAFT,
                'keterangan' => $this->nullableText($data['keterangan'] ?? null),
                'nomor_referensi' => $this->nullableText($data['nomor_referensi'] ?? null),
                'created_by' => $financeUserId,
                'updated_by' => $financeUserId,
                'idempotency_key' => $data['idempotency_key'] ?? (string) Str::uuid(),
            ]);

            $beban->details()->createMany($detailRows);

            return $beban->fresh(['details.akun', 'dompet.akun', 'creator']);
        });
    }

    public function updateDraft(BebanOperasional $beban, array $data, int $financeUserId): BebanOperasional
    {
        return DB::transaction(function () use ($beban, $data, $financeUserId): BebanOperasional {
            $locked = BebanOperasional::query()
                ->with('details')
                ->lockForUpdate()
                ->findOrFail($beban->id);

            $this->assertStatus($locked, [BebanOperasional::STATUS_DRAFT], 'Beban Operasional yang sudah posted tidak dapat diedit.');

            $tanggal = $this->normalizeDate($data['tanggal_beban']);
            $detailRows = $this->buildDraftDetailRows($data);
            $total = $this->sumDetails($detailRows);
            $dompet = $this->resolveDraftDompet((int) ($data['dompet_id'] ?? 0));
            $metode = $this->metodeFromDompet($dompet);

            $locked->details->each->delete();
            $locked->details()->createMany($detailRows);

            $locked->update([
                'tanggal_beban' => $tanggal->toDateString(),
                'dompet_id' => $dompet->id,
                'metode_pembayaran' => $metode,
                'total_beban' => $this->rupiahDecimal($total),
                'keterangan' => $this->nullableText($data['keterangan'] ?? null),
                'nomor_referensi' => $this->nullableText($data['nomor_referensi'] ?? null),
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['details.akun', 'dompet.akun', 'updater']);
        });
    }

    public function cancelDraft(BebanOperasional $beban, int $financeUserId): void
    {
        DB::transaction(function () use ($beban, $financeUserId): void {
            $locked = BebanOperasional::query()
                ->with('details')
                ->lockForUpdate()
                ->findOrFail($beban->id);

            $this->assertStatus($locked, [BebanOperasional::STATUS_DRAFT], 'Hanya draft Beban Operasional yang dapat dibatalkan tanpa reversal.');

            if ($this->hasPosting($locked)) {
                throw ValidationException::withMessages([
                    'beban' => 'Draft ini sudah mempunyai Mutasi/Jurnal sehingga tidak dapat dibatalkan sebagai draft.',
                ]);
            }

            $locked->update(['updated_by' => $financeUserId]);
            $locked->details->each->delete();
            $locked->delete();
        });
    }

    public function post(BebanOperasional $beban, ?int $dompetId, int $financeUserId): BebanOperasional
    {
        return DB::transaction(function () use ($beban, $dompetId, $financeUserId): BebanOperasional {
            $locked = BebanOperasional::query()
                ->with(['details.akun', 'dompet.akun'])
                ->lockForUpdate()
                ->findOrFail($beban->id);

            $this->assertStatus($locked, [BebanOperasional::STATUS_DRAFT], 'Hanya draft Beban Operasional yang dapat diposting.');

            if ($locked->details->isEmpty()) {
                throw ValidationException::withMessages([
                    'details' => 'Beban Operasional wajib mempunyai minimal satu detail.',
                ]);
            }

            if ($locked->details->count() !== 1) {
                throw ValidationException::withMessages([
                    'details' => 'Beban Operasional baru hanya boleh mempunyai satu detail akun dan nominal.',
                ]);
            }

            $this->lockDetails($locked);
            $snapshotRows = $this->buildPostingSnapshots($locked->details);
            $total = $this->sumDetails($snapshotRows);
            $postingDompetId = $dompetId ?: (int) $locked->dompet_id;

            if ($postingDompetId <= 0) {
                throw ValidationException::withMessages([
                    'dompet_id' => 'Dompet Kas/Bank wajib dipilih sebelum posting Beban Operasional.',
                ]);
            }

            $dompet = DompetKoperasi::query()
                ->with('akun')
                ->lockForUpdate()
                ->findOrFail($postingDompetId);
            $metode = $this->metodeFromDompet($dompet);

            if ($this->rupiahInt($dompet->saldo) < $total) {
                throw ValidationException::withMessages([
                    'dompet_id' => 'Saldo Dompet tidak mencukupi untuk posting Beban Operasional.',
                ]);
            }

            foreach ($snapshotRows as $row) {
                BebanOperasionalDetail::query()
                    ->where('beban_operasional_id', $locked->id)
                    ->where('id', $row['id'])
                    ->update([
                        'kode_akun_snapshot' => $row['kode_akun_snapshot'],
                        'nama_akun_snapshot' => $row['nama_akun_snapshot'],
                        'kode_aset_snapshot' => $row['kode_aset_snapshot'],
                        'nama_aset_snapshot' => $row['nama_aset_snapshot'],
                        'nominal' => $row['nominal'],
                    ]);
            }

            $locked->update([
                'dompet_id' => $dompet->id,
                'metode_pembayaran' => $metode,
                'total_beban' => $this->rupiahDecimal($total),
                'status' => BebanOperasional::STATUS_POSTED,
                'posted_at' => now(),
                'posted_by' => $financeUserId,
                'updated_by' => $financeUserId,
            ]);

            $this->decreaseSaldoDompet($dompet, $total);
            $this->recordPostingMutasi($locked->fresh(), $dompet, $total);
            $this->akuntansiService->recordBebanOperasionalPosting($locked->fresh(['details.akun']), $dompet->akun, $financeUserId);

            return $locked->fresh(['details.akun', 'dompet.akun', 'jurnal.details']);
        });
    }

    public function reverse(BebanOperasional $beban, string $alasan, int $financeUserId): BebanOperasional
    {
        return DB::transaction(function () use ($beban, $alasan, $financeUserId): BebanOperasional {
            $locked = BebanOperasional::query()
                ->with(['details.akun', 'dompet.akun', 'reversal'])
                ->lockForUpdate()
                ->findOrFail($beban->id);

            $this->assertStatus($locked, [BebanOperasional::STATUS_POSTED], 'Hanya Beban Operasional posted yang dapat direversal.');

            if ($locked->reversal_transaksi_id || $locked->reversal) {
                throw ValidationException::withMessages([
                    'reversal' => 'Beban Operasional ini sudah mempunyai reversal.',
                ]);
            }

            $dompet = DompetKoperasi::query()
                ->with('akun')
                ->lockForUpdate()
                ->findOrFail((int) $locked->dompet_id);
            $this->assertDompetAkun($dompet);

            $originalMutasi = MutasiKas::query()
                ->where('referensi_tipe', BebanOperasional::class)
                ->where('referensi_id', $locked->id)
                ->where('idempotency_key', 'beban-operasional:posting:mutasi:' . $locked->id)
                ->lockForUpdate()
                ->first();
            $originalJurnal = JurnalUmum::query()
                ->where('referensi_tipe', BebanOperasional::class)
                ->where('referensi_id', $locked->id)
                ->where('idempotency_key', 'beban-operasional:posting:jurnal:' . $locked->id)
                ->lockForUpdate()
                ->first();

            if (! $originalMutasi || ! $originalJurnal) {
                throw ValidationException::withMessages([
                    'reversal' => 'Mutasi Kas atau Jurnal asli Beban Operasional tidak lengkap. Jalankan preflight dan rekonsiliasi manual sebelum reversal.',
                ]);
            }

            $total = $this->rupiahInt($locked->total_beban);

            $reversal = ReversalTransaksi::query()->create([
                'kode_reversal' => $this->nextCode('reversal', 'REV', CarbonImmutable::now(config('app.timezone', 'Asia/Jakarta'))),
                'source_type' => BebanOperasional::class,
                'source_id' => $locked->id,
                'jenis_reversal' => ReversalTransaksi::JENIS_BEBAN_OPERASIONAL_REVERSAL,
                'nominal' => $this->rupiahDecimal($total),
                'alasan' => $this->normalizeText($alasan),
                'status' => ReversalTransaksi::STATUS_PROCESSED,
                'original_mutasi_id' => $originalMutasi?->id,
                'original_jurnal_id' => $originalJurnal?->id,
                'dompet_refund_id' => $dompet->id,
                'created_by' => $financeUserId,
                'processed_by' => $financeUserId,
                'processed_at' => now(),
                'idempotency_key' => 'reversal:beban-operasional:' . $locked->id,
            ]);

            $this->increaseSaldoDompet($dompet, $total);
            $this->recordReversalMutasi($locked, $reversal, $dompet, $total);
            $this->akuntansiService->recordBebanOperasionalReversal($locked, $reversal, $dompet->akun, $financeUserId);

            $locked->update([
                'status' => BebanOperasional::STATUS_REVERSED,
                'reversed_at' => now(),
                'reversed_by' => $financeUserId,
                'alasan_reversal' => $this->normalizeText($alasan),
                'reversal_transaksi_id' => $reversal->id,
                'updated_by' => $financeUserId,
            ]);

            return $locked->fresh(['details.akun', 'dompet.akun', 'reversal']);
        });
    }

    private function buildDraftDetailRows(array $data): array
    {
        $details = $this->extractSingleDetail($data);

        if ($details === []) {
            throw ValidationException::withMessages([
                'details' => 'Beban Operasional wajib mempunyai satu detail akun dan nominal.',
            ]);
        }

        if (count($details) !== 1) {
            throw ValidationException::withMessages([
                'details' => 'Beban Operasional baru hanya boleh mempunyai satu akun Beban dan satu nominal.',
            ]);
        }

        $rows = [];

        foreach ($details as $detail) {
            $akun = Akun::query()->findOrFail((int) ($detail['akun_id'] ?? 0));
            $this->assertBebanAkunStructure($akun);

            $nominal = $this->rupiahInt($detail['nominal'] ?? 0);
            if ($nominal <= 0) {
                throw ValidationException::withMessages([
                    'details' => 'Nominal setiap detail Beban wajib lebih besar dari nol.',
                ]);
            }

            $keterangan = $this->normalizeText((string) ($detail['keterangan'] ?? ''));
            if ($keterangan === '') {
                throw ValidationException::withMessages([
                    'details' => 'Keterangan setiap detail Beban wajib diisi agar audit trail jelas.',
                ]);
            }

            $rows[] = [
                'akun_id' => $akun->id,
                'aset_koperasi_id' => null,
                'keterangan' => $keterangan,
                'nominal' => $this->rupiahDecimal($nominal),
            ];
        }

        return $rows;
    }

    private function buildPostingSnapshots(Collection $details): array
    {
        return $details->map(function (BebanOperasionalDetail $detail): array {
            $akun = Akun::query()->lockForUpdate()->findOrFail($detail->akun_id);
            $this->assertBebanAkunForPosting($akun);

            $nominal = $this->rupiahInt($detail->nominal);
            if ($nominal <= 0) {
                throw ValidationException::withMessages([
                    'details' => 'Nominal setiap detail Beban wajib lebih besar dari nol.',
                ]);
            }

            return [
                'id' => $detail->id,
                'akun_id' => $akun->id,
                'aset_koperasi_id' => null,
                'kode_akun_snapshot' => $akun->kode_akun,
                'nama_akun_snapshot' => $akun->nama_akun,
                'kode_aset_snapshot' => null,
                'nama_aset_snapshot' => null,
                'nominal' => $this->rupiahDecimal($nominal),
            ];
        })->values()->all();
    }

    private function extractSingleDetail(array $data): array
    {
        if (array_key_exists('akun_id', $data) || array_key_exists('nominal', $data)) {
            return [[
                'akun_id' => $data['akun_id'] ?? null,
                'keterangan' => $data['keterangan'] ?? null,
                'nominal' => $data['nominal'] ?? null,
            ]];
        }

        return array_values($data['details'] ?? []);
    }

    private function resolveDraftDompet(int $dompetId): DompetKoperasi
    {
        if ($dompetId <= 0) {
            throw ValidationException::withMessages([
                'dompet_id' => 'Dompet Kas/Bank wajib dipilih.',
            ]);
        }

        $dompet = DompetKoperasi::query()
            ->with('akun')
            ->findOrFail($dompetId);

        $this->assertDompetAkun($dompet);

        return $dompet;
    }

    private function lockDetails(BebanOperasional $beban): void
    {
        BebanOperasionalDetail::query()
            ->where('beban_operasional_id', $beban->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    private function sumDetails(array $rows): int
    {
        return collect($rows)->sum(fn (array $row) => $this->rupiahInt($row['nominal'] ?? 0));
    }

    private function hasPosting(BebanOperasional $beban): bool
    {
        return MutasiKas::query()->where('referensi_tipe', BebanOperasional::class)->where('referensi_id', $beban->id)->exists()
            || JurnalUmum::query()->where('referensi_tipe', BebanOperasional::class)->where('referensi_id', $beban->id)->exists();
    }

    private function recordPostingMutasi(BebanOperasional $beban, DompetKoperasi $dompet, int $total): MutasiKas
    {
        return MutasiKas::query()->firstOrCreate(
            ['idempotency_key' => 'beban-operasional:posting:mutasi:' . $beban->id],
            [
                'dompet_id' => $dompet->id,
                'tipe' => 'keluar',
                'jumlah' => $this->rupiahDecimal($total),
                'keterangan' => 'Posting Beban Operasional ' . $beban->kode_beban,
                'referensi_tipe' => BebanOperasional::class,
                'referensi_id' => $beban->id,
                'tanggal' => $beban->tanggal_beban->toDateString(),
            ]
        );
    }

    private function recordReversalMutasi(BebanOperasional $beban, ReversalTransaksi $reversal, DompetKoperasi $dompet, int $total): MutasiKas
    {
        return MutasiKas::query()->firstOrCreate(
            ['idempotency_key' => 'beban-operasional:reversal:mutasi:' . $reversal->id],
            [
                'dompet_id' => $dompet->id,
                'tipe' => 'masuk',
                'jumlah' => $this->rupiahDecimal($total),
                'keterangan' => 'Reversal penuh Beban Operasional ' . $beban->kode_beban,
                'referensi_tipe' => ReversalTransaksi::class,
                'referensi_id' => $reversal->id,
                'tanggal' => now()->toDateString(),
            ]
        );
    }

    private function metodeFromDompet(DompetKoperasi $dompet): string
    {
        $this->assertDompetAkun($dompet);

        return match ($dompet->jenis_dompet) {
            DompetKoperasi::JENIS_KAS => BebanOperasional::METODE_TUNAI,
            DompetKoperasi::JENIS_BANK => BebanOperasional::METODE_TRANSFER_BANK,
            default => throw ValidationException::withMessages([
                'dompet_id' => 'Beban Operasional hanya dapat dibayar dari Dompet Kas atau Bank.',
            ]),
        };
    }

    private function assertDompetAkun(DompetKoperasi $dompet): void
    {
        if (! in_array($dompet->jenis_dompet, [DompetKoperasi::JENIS_KAS, DompetKoperasi::JENIS_BANK], true)) {
            throw ValidationException::withMessages([
                'dompet_id' => 'Beban Operasional hanya dapat dibayar dari Dompet Kas atau Bank.',
            ]);
        }

        if (! $dompet->akun || ! $dompet->akun->is_aktif || $dompet->akun->kategori !== 'aset' || $dompet->akun->posisi_saldo !== 'debit') {
            throw ValidationException::withMessages([
                'dompet_id' => 'Dompet wajib memiliki mapping COA Aset aktif dengan saldo normal Debit.',
            ]);
        }
    }

    private function assertBebanAkunStructure(Akun $akun): void
    {
        if (! $akun->is_aktif) {
            throw ValidationException::withMessages([
                'akun_id' => 'Akun Beban wajib aktif untuk Beban Operasional.',
            ]);
        }

        if ($akun->kategori !== 'beban' || $akun->posisi_saldo !== 'debit') {
            throw ValidationException::withMessages([
                'akun_id' => 'Detail Beban Operasional wajib memakai akun kategori Beban dengan saldo normal Debit.',
            ]);
        }

        if ($this->isHargaPokokPenjualanAccount($akun)) {
            throw ValidationException::withMessages([
                'akun_id' => 'Akun HPP tidak boleh dipakai untuk Beban Operasional.',
            ]);
        }

        if (! $akun->is_beban_operasional) {
            throw ValidationException::withMessages([
                'akun_id' => 'Akun ini tidak ditandai eligible untuk Beban Operasional. Aktifkan eligibility COA terlebih dahulu.',
            ]);
        }
    }

    private function isHargaPokokPenjualanAccount(Akun $akun): bool
    {
        $hppKode = config('account_map.accounts.harga_pokok_penjualan.kode_akun');

        return $hppKode !== null && (string) $akun->kode_akun === (string) $hppKode;
    }

    private function assertBebanAkunForPosting(Akun $akun): void
    {
        $this->assertBebanAkunStructure($akun);

        if (! $akun->is_aktif) {
            throw ValidationException::withMessages([
                'akun_id' => 'Akun Beban sudah nonaktif. Pilih akun Beban aktif sebelum posting.',
            ]);
        }
    }

    private function assertStatus(BebanOperasional $beban, array $allowed, string $message): void
    {
        if (! in_array($beban->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => $message,
            ]);
        }
    }

    private function nextCode(string $jenis, string $prefix, CarbonImmutable $date): string
    {
        $periode = $date
            ->setTimezone(config('app.timezone', 'Asia/Jakarta'))
            ->format('Ym');

        DB::table('nomor_urut_transaksi')->insertOrIgnore([
            'jenis' => $jenis,
            'periode' => $periode,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $counter = DB::table('nomor_urut_transaksi')
            ->where('jenis', $jenis)
            ->where('periode', $periode)
            ->lockForUpdate()
            ->first();

        $next = ((int) $counter->last_number) + 1;

        DB::table('nomor_urut_transaksi')
            ->where('jenis', $jenis)
            ->where('periode', $periode)
            ->update([
                'last_number' => $next,
                'updated_at' => now(),
            ]);

        return sprintf('%s-%s-%06d', $prefix, $periode, $next);
    }

    private function increaseSaldoDompet(DompetKoperasi $dompet, int $jumlah): void
    {
        $dompet->update([
            'saldo' => $this->rupiahDecimal($this->rupiahInt($dompet->saldo) + $jumlah),
        ]);
    }

    private function decreaseSaldoDompet(DompetKoperasi $dompet, int $jumlah): void
    {
        $dompet->update([
            'saldo' => $this->rupiahDecimal($this->rupiahInt($dompet->saldo) - $jumlah),
        ]);
    }

    private function normalizeDate(mixed $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, config('app.timezone', 'Asia/Jakarta'))
            ->setTimezone(config('app.timezone', 'Asia/Jakarta'))
            ->startOfDay();
    }

    private function normalizeText(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    private function nullableText(?string $value): ?string
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', (string) $value));

        return $normalized === '' ? null : $normalized;
    }

    private function rupiahInt(mixed $value): int
    {
        return (int) round((float) $value);
    }

    private function rupiahDecimal(int $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
