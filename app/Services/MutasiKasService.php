<?php

namespace App\Services;

use App\Models\CicilanPinjaman;
use App\Models\DompetKoperasi;
use App\Models\MutasiKas;
use App\Models\Penjualan;
use App\Models\Pinjaman;
use App\Models\Simpanan;
use RuntimeException;

class MutasiKasService
{
    public function hasAvailableDompet(): bool
    {
        return DompetKoperasi::query()->exists();
    }

    public function record(array $data): MutasiKas
    {
        $dompet = $this->resolveDompet($data['dompet_id'] ?? null);
        $jumlah = round((float) $data['jumlah'], 2);

        $mutasi = MutasiKas::create([
            'dompet_id' => $dompet->id,
            'tipe' => $data['tipe'],
            'jumlah' => $jumlah,
            'keterangan' => $data['keterangan'] ?? null,
            'referensi_tipe' => $data['referensi_tipe'] ?? null,
            'referensi_id' => $data['referensi_id'] ?? null,
            'tanggal' => $data['tanggal'],
        ]);

        $this->applySaldo($dompet, $data['tipe'], $jumlah);

        return $mutasi;
    }

    public function reverseByReference(string $referensiTipe, int $referensiId): void
    {
        MutasiKas::where('referensi_tipe', $referensiTipe)
            ->where('referensi_id', $referensiId)
            ->get()
            ->each(fn (MutasiKas $mutasi) => $this->deleteAndReverse($mutasi));
    }

    public function backfillHistoricalTransactions(): void
    {
        throw new RuntimeException('Backfill historis Mutasi Kas & Bank dinonaktifkan. Mutasi hanya boleh dibuat oleh service transaksi resmi dengan Dompet eksplisit.');
    }

    public function deleteAndReverse(MutasiKas $mutasi): void
    {
        if ($mutasi->dompet) {
            $this->reverseSaldo($mutasi->dompet, $mutasi->tipe, (float) $mutasi->jumlah);
        }

        $mutasi->delete();
    }

    protected function resolveDompet(?int $dompetId = null): DompetKoperasi
    {
        if (! $dompetId) {
            throw new RuntimeException('Dompet wajib ditentukan secara eksplisit untuk mencatat Mutasi Kas & Bank.');
        }

        $dompet = DompetKoperasi::find($dompetId);

        if (! $dompet) {
            throw new RuntimeException('Dompet koperasi yang dipilih tidak ditemukan untuk mencatat Mutasi Kas & Bank.');
        }

        return $dompet;
    }

    protected function applySaldo(DompetKoperasi $dompet, string $tipe, float $jumlah): void
    {
        $saldoBaru = $tipe === 'masuk'
            ? (float) $dompet->saldo + $jumlah
            : (float) $dompet->saldo - $jumlah;

        $dompet->update([
            'saldo' => round($saldoBaru, 2),
        ]);
    }

    protected function reverseSaldo(DompetKoperasi $dompet, string $tipe, float $jumlah): void
    {
        $saldoBaru = $tipe === 'masuk'
            ? (float) $dompet->saldo - $jumlah
            : (float) $dompet->saldo + $jumlah;

        $dompet->update([
            'saldo' => round($saldoBaru, 2),
        ]);
    }

    protected function backfillPenjualan(): void
    {
        Penjualan::query()
            ->whereDoesntHave('mutasiKas')
            ->orderBy('id')
            ->get()
            ->each(function (Penjualan $penjualan) {
                $metode = $penjualan->pembayaran?->metode_pembayaran ?? 'tunai';
                if ($metode !== 'tunai') {
                    return;
                }
                $this->record([
                    'tipe' => 'masuk',
                    'jumlah' => $penjualan->grand_total,
                    'keterangan' => 'Penerimaan dari penjualan ' . $penjualan->kode_transaksi,
                    'referensi_tipe' => Penjualan::class,
                    'referensi_id' => $penjualan->id,
                    'tanggal' => optional($penjualan->created_at)->toDateString() ?? now()->toDateString(),
                ]);
            });
    }

    protected function backfillSimpanan(): void
    {
        Simpanan::query()
            ->whereDoesntHave('mutasiKas')
            ->orderBy('id')
            ->get()
            ->each(function (Simpanan $simpanan) {
                $this->record([
                    'tipe' => 'masuk',
                    'jumlah' => $simpanan->jumlah,
                    'keterangan' => 'Penerimaan simpanan karyawan',
                    'referensi_tipe' => Simpanan::class,
                    'referensi_id' => $simpanan->id,
                    'tanggal' => $simpanan->tanggal,
                ]);
            });
    }

    protected function backfillPinjaman(): void
    {
        Pinjaman::query()
            ->whereDoesntHave('mutasiKas')
            ->orderBy('id')
            ->get()
            ->each(function (Pinjaman $pinjaman) {
                $this->record([
                    'tipe' => 'keluar',
                    'jumlah' => $pinjaman->jumlah_pinjaman,
                    'keterangan' => 'Pencairan pinjaman karyawan',
                    'referensi_tipe' => Pinjaman::class,
                    'referensi_id' => $pinjaman->id,
                    'tanggal' => $pinjaman->tanggal_pinjaman,
                ]);
            });
    }

    protected function backfillCicilanPinjaman(): void
    {
        CicilanPinjaman::query()
            ->where('status', 'sudah_bayar')
            ->whereDoesntHave('mutasiKas')
            ->orderBy('id')
            ->get()
            ->each(function (CicilanPinjaman $cicilanPinjaman) {
                $this->record([
                    'tipe' => 'masuk',
                    'jumlah' => $cicilanPinjaman->jumlah_cicilan,
                    'keterangan' => 'Pembayaran cicilan pinjaman',
                    'referensi_tipe' => CicilanPinjaman::class,
                    'referensi_id' => $cicilanPinjaman->id,
                    'tanggal' => $cicilanPinjaman->tanggal_bayar ?? optional($cicilanPinjaman->created_at)->toDateString() ?? now()->toDateString(),
                ]);
            });
    }
}
