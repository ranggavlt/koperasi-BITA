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
use App\Services\SimpananManasukaService;

class SimpananManualService
{
    public function __construct(
        private readonly MutasiKasService $mutasiKasService,
        private readonly AkuntansiService $akuntansiService
    ) {
    }

    public function create(array $data, ?int $userId = null): Simpanan
    {
        if (empty($data['metode_pembayaran']) && ! empty($data['dompet_id'])) {
            $jenisDompet = DompetKoperasi::query()
                ->whereKey((int) $data['dompet_id'])
                ->value('jenis_dompet');

            $data['metode_pembayaran'] = $jenisDompet === DompetKoperasi::JENIS_BANK
                ? Simpanan::METODE_TRANSFER_BANK
                : Simpanan::METODE_TUNAI;
        }

        $data['jenis_transaksi'] = Simpanan::JENIS_SETORAN;

        return app(SimpananManasukaService::class)->setoran($data, $userId);
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
