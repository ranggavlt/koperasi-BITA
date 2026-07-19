<?php

namespace App\Services;

use App\Models\Anggota;
use App\Models\Akun;
use App\Models\DompetKoperasi;
use App\Models\JenisSimpanan;
use App\Models\MutasiKas;
use App\Models\Simpanan;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SimpananManualService
{
    public function __construct(
        private readonly MutasiKasService $mutasiKasService,
        private readonly AkuntansiService $akuntansiService
    ) {
    }

    public function create(array $data, ?int $userId = null): Simpanan
    {
        $idempotencyKey = $this->normalizeIdempotencyKey($data['idempotency_key'] ?? null);

        try {
            return DB::transaction(function () use ($data, $userId, $idempotencyKey): Simpanan {
                $existing = Simpanan::query()
                    ->with(['mutasiKas.dompet.akun', 'jurnal.details.akun'])
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $existing;
                }

                $anggota = Anggota::query()
                    ->with('karyawan')
                    ->lockForUpdate()
                    ->findOrFail((int) $data['anggota_id']);
                $jenis = JenisSimpanan::query()
                    ->with('akun')
                    ->lockForUpdate()
                    ->findOrFail((int) $data['jenis_simpanan_id']);
                $dompet = DompetKoperasi::query()
                    ->with('akun')
                    ->lockForUpdate()
                    ->findOrFail((int) $data['dompet_id']);

                $this->assertManualJenis($jenis);
                $this->assertAkunSimpanan($jenis->akun);
                $this->assertDompetAkun($dompet);

                $jumlah = $this->rupiahInt($data['jumlah'] ?? 0);
                if ($jumlah <= 0) {
                    throw ValidationException::withMessages([
                        'jumlah' => 'Jumlah simpanan wajib lebih besar dari nol.',
                    ]);
                }

                $siklusId = $anggota->siklusAktif()->value('id');

                $simpanan = Simpanan::query()->create([
                    'idempotency_key' => $idempotencyKey,
                    'anggota_id' => $anggota->id,
                    'karyawan_id' => $anggota->karyawan_id,
                    'siklus_keanggotaan_id' => $siklusId,
                    'jenis_simpanan_id' => $jenis->id,
                    'kode_jenis_snapshot' => $jenis->kode,
                    'nama_jenis_snapshot' => $jenis->nama_jenis,
                    'nominal_snapshot' => $this->rupiahDecimal($jumlah),
                    'jumlah' => $this->rupiahDecimal($jumlah),
                    'metode_pembayaran' => Simpanan::METODE_TUNAI,
                    'status' => Simpanan::STATUS_SETTLED,
                    'settled_at' => now(),
                    'tanggal' => $data['tanggal'],
                    'keterangan' => $data['keterangan'] ?? null,
                    'created_by' => $userId,
                ]);

                $mutasi = $this->mutasiKasService->record([
                    'idempotency_key' => 'simpanan:manual:mutasi:' . $idempotencyKey,
                    'dompet_id' => $dompet->id,
                    'tipe' => 'masuk',
                    'jumlah' => $jumlah,
                    'keterangan' => 'Penerimaan simpanan anggota',
                    'referensi_tipe' => Simpanan::class,
                    'referensi_id' => $simpanan->id,
                    'tanggal' => $data['tanggal'],
                ]);

                $jurnal = $this->akuntansiService->recordSimpanan(
                    $simpanan->fresh('jenisSimpanan.akun'),
                    $dompet->akun,
                    $userId,
                    'simpanan:manual:jurnal:' . $idempotencyKey
                );

                $this->assertPostingConsistent($mutasi, $jurnal->fresh('details'), $dompet);

                return $simpanan->fresh(['anggota.karyawan', 'jenisSimpanan.akun', 'mutasiKas.dompet.akun', 'jurnal.details.akun']);
            });
        } catch (UniqueConstraintViolationException) {
            $existing = Simpanan::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing->fresh(['anggota.karyawan', 'jenisSimpanan.akun', 'mutasiKas.dompet.akun', 'jurnal.details.akun']);
            }

            throw ValidationException::withMessages([
                'simpanan' => 'Transaksi Simpanan gagal karena konflik idempotency. Silakan muat ulang form.',
            ]);
        }
    }

    private function assertManualJenis(JenisSimpanan $jenis): void
    {
        if (! $jenis->aktif) {
            throw ValidationException::withMessages([
                'jenis_simpanan_id' => 'Jenis Simpanan yang dipilih sedang nonaktif.',
            ]);
        }

        if ($jenis->kode === JenisSimpanan::KODE_SIMPANAN_POKOK || $jenis->kategori === JenisSimpanan::KATEGORI_POKOK) {
            throw ValidationException::withMessages([
                'jenis_simpanan_id' => 'Simpanan Pokok dibuat otomatis saat Anggota dibuat dan tidak boleh diinput manual.',
            ]);
        }

        if ($jenis->kode === JenisSimpanan::KODE_SIMPANAN_WAJIB || $jenis->kategori === JenisSimpanan::KATEGORI_WAJIB) {
            throw ValidationException::withMessages([
                'jenis_simpanan_id' => 'Simpanan Wajib dibuat otomatis dari jadwal payroll dan tidak boleh diinput manual.',
            ]);
        }
    }

    private function assertAkunSimpanan(?Akun $akun): void
    {
        if (! $akun) {
            throw ValidationException::withMessages([
                'jenis_simpanan_id' => 'Jenis Simpanan belum memiliki pemetaan COA.',
            ]);
        }

        if (! $akun->is_aktif || ! in_array($akun->kategori, ['kewajiban', 'ekuitas'], true) || $akun->posisi_saldo !== 'kredit') {
            throw ValidationException::withMessages([
                'jenis_simpanan_id' => 'COA Jenis Simpanan wajib aktif, kategori Kewajiban/Ekuitas, dan saldo normal Kredit.',
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

    private function assertPostingConsistent(MutasiKas $mutasi, $jurnal, DompetKoperasi $dompet): void
    {
        if ((int) $mutasi->dompet_id !== (int) $dompet->id) {
            throw ValidationException::withMessages([
                'dompet_id' => 'Mutasi Simpanan tidak merujuk Dompet yang sama dengan transaksi.',
            ]);
        }

        $debit = $jurnal->details
            ->where('debit', '>', 0)
            ->first();

        if (! $debit || (int) $debit->akun_id !== (int) $dompet->akun_id) {
            throw ValidationException::withMessages([
                'dompet_id' => 'Jurnal debit Simpanan tidak memakai COA Dompet yang dipilih.',
            ]);
        }
    }

    private function normalizeIdempotencyKey(?string $key): string
    {
        $normalized = trim((string) $key);

        return $normalized !== '' ? $normalized : 'simpanan:manual:' . (string) Str::uuid();
    }

    private function rupiahInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        $string = trim((string) $value);

        if ($string === '') {
            return 0;
        }

        if (preg_match('/^\d+(\.\d{1,2})?$/', $string) === 1) {
            return (int) explode('.', $string)[0];
        }

        return (int) preg_replace('/[^\d]/', '', $string);
    }

    private function rupiahDecimal(mixed $value): string
    {
        return number_format($this->rupiahInt($value), 2, '.', '');
    }
}
