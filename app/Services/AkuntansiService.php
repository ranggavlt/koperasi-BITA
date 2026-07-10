<?php

namespace App\Services;

use App\Models\Akun;
use App\Models\CicilanPinjaman;
use App\Models\JurnalUmum;
use App\Models\PembayaranKonsinyasi;
use App\Models\Penjualan;
use App\Models\Pinjaman;
use App\Models\Simpanan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AkuntansiService
{
    public function __construct(private readonly AkunResolver $akunResolver)
    {
    }

    /**
     * @param  array<int, array{akun_id:int, akun_kode:string, akun_nama:string, debit:float|int, kredit:float|int}>  $lines
     */
    public function record(array $header, array $lines): JurnalUmum
    {
        if (count($lines) < 2) {
            throw new RuntimeException('Jurnal harus memiliki minimal dua baris akun.');
        }

        $normalizedLines = collect($lines)
            ->map(fn (array $line) => $this->normalizeLine($line))
            ->values()
            ->all();

        $totalDebit = round(collect($normalizedLines)->sum(fn ($line) => $line['debit']), 2);
        $totalKredit = round(collect($normalizedLines)->sum(fn ($line) => $line['kredit']), 2);

        if ($totalDebit <= 0 || $totalKredit <= 0 || abs($totalDebit - $totalKredit) > 0.01) {
            throw new RuntimeException('Jurnal tidak balance (debit != kredit).');
        }

        return DB::transaction(function () use ($header, $normalizedLines): JurnalUmum {
            $jurnal = JurnalUmum::create($header);

            $jurnal->details()->createMany($normalizedLines);

            return $jurnal;
        });
    }

    public function reverseByReference(string $referensiTipe, int $referensiId): void
    {
        JurnalUmum::query()
            ->where('referensi_tipe', $referensiTipe)
            ->where('referensi_id', $referensiId)
            ->get()
            ->each(fn (JurnalUmum $jurnal) => $jurnal->delete());
    }

    public function recordPenjualan(Penjualan $penjualan, string $metodePembayaran): void
    {
        if (JurnalUmum::query()
            ->where('referensi_tipe', Penjualan::class)
            ->where('referensi_id', $penjualan->id)
            ->exists()) {
            return;
        }

        $tanggal = optional($penjualan->created_at)->toDateString() ?? now()->toDateString();

        $totalSetorKonsinyasi = (float) $penjualan->details()
            ->where('konsinyasi', true)
            ->sum('subtotal_setor');

        $grandTotal = (float) ($penjualan->grand_total ?? 0);
        $pendapatan = max(0, $grandTotal - $totalSetorKonsinyasi);

        $akunDebit = $metodePembayaran === 'potong_gaji'
            ? $this->akunResolver->posting('penjualan.piutang_potong_gaji')
            : $this->akunResolver->posting('penjualan.kas');

        $lines = [
            $this->akunResolver->line($akunDebit, 'debit', $grandTotal),
        ];

        if ($totalSetorKonsinyasi > 0) {
            $lines[] = $this->akunResolver->line(
                $this->akunResolver->posting('penjualan.utang_konsinyasi'),
                'kredit',
                $totalSetorKonsinyasi
            );
        }

        if ($pendapatan > 0) {
            $lines[] = $this->akunResolver->line(
                $this->akunResolver->posting('penjualan.pendapatan'),
                'kredit',
                $pendapatan
            );
        }

        $this->record([
            'tanggal' => $tanggal,
            'nomor_bukti' => $penjualan->kode_transaksi,
            'keterangan' => 'Penjualan ' . $penjualan->kode_transaksi . ' (' . $metodePembayaran . ')',
            'referensi_tipe' => Penjualan::class,
            'referensi_id' => $penjualan->id,
            'created_by' => auth()->id(),
        ], $lines);
    }

    public function recordSimpanan(Simpanan $simpanan): void
    {
        if (JurnalUmum::query()
            ->where('referensi_tipe', Simpanan::class)
            ->where('referensi_id', $simpanan->id)
            ->exists()) {
            return;
        }

        $jumlah = (float) ($simpanan->jumlah ?? 0);
        $tanggal = (string) ($simpanan->tanggal ?? now()->toDateString());
        $simpanan->loadMissing('jenisSimpanan.akun');
        $akunSimpanan = $simpanan->jenisSimpanan?->akun;

        if (! $akunSimpanan) {
            throw new RuntimeException(
                'Jenis simpanan belum memiliki pemetaan ke master COA.'
            );
        }

        if (! $akunSimpanan->is_aktif || ! in_array($akunSimpanan->kategori, ['kewajiban', 'ekuitas'], true)) {
            throw new RuntimeException('Akun jenis simpanan harus aktif dan berkategori kewajiban atau ekuitas.');
        }

        $this->record([
            'tanggal' => $tanggal,
            'nomor_bukti' => 'SIMP-' . $simpanan->id,
            'keterangan' => 'Setoran simpanan',
            'referensi_tipe' => Simpanan::class,
            'referensi_id' => $simpanan->id,
            'created_by' => auth()->id(),
        ], [
            $this->akunResolver->line(
                $this->akunResolver->posting('simpanan.kas'),
                'debit',
                $jumlah
            ),
            $this->akunResolver->line(
                $akunSimpanan,
                'kredit',
                $jumlah
            ),
        ]);
    }

    public function recordPinjaman(Pinjaman $pinjaman): void
    {
        if (JurnalUmum::query()
            ->where('referensi_tipe', Pinjaman::class)
            ->where('referensi_id', $pinjaman->id)
            ->exists()) {
            return;
        }

        $jumlah = (float) ($pinjaman->jumlah_pinjaman ?? 0);
        $tanggal = (string) ($pinjaman->tanggal_pinjaman ?? now()->toDateString());

        $this->record([
            'tanggal' => $tanggal,
            'nomor_bukti' => 'PJM-' . $pinjaman->id,
            'keterangan' => 'Pencairan pinjaman',
            'referensi_tipe' => Pinjaman::class,
            'referensi_id' => $pinjaman->id,
            'created_by' => auth()->id(),
        ], [
            $this->akunResolver->line(
                $this->akunResolver->posting('pinjaman.piutang'),
                'debit',
                $jumlah
            ),
            $this->akunResolver->line(
                $this->akunResolver->posting('pinjaman.kas'),
                'kredit',
                $jumlah
            ),
        ]);
    }

    public function recordCicilan(CicilanPinjaman $cicilan): void
    {
        if (JurnalUmum::query()
            ->where('referensi_tipe', CicilanPinjaman::class)
            ->where('referensi_id', $cicilan->id)
            ->exists()) {
            return;
        }

        $jumlah = (float) ($cicilan->jumlah_cicilan ?? 0);
        $tanggal = (string) ($cicilan->tanggal_bayar ?? optional($cicilan->created_at)->toDateString() ?? now()->toDateString());

        $this->record([
            'tanggal' => $tanggal,
            'nomor_bukti' => 'CIC-' . $cicilan->id,
            'keterangan' => 'Pembayaran cicilan pinjaman',
            'referensi_tipe' => CicilanPinjaman::class,
            'referensi_id' => $cicilan->id,
            'created_by' => auth()->id(),
        ], [
            $this->akunResolver->line(
                $this->akunResolver->posting('pinjaman.kas'),
                'debit',
                $jumlah
            ),
            $this->akunResolver->line(
                $this->akunResolver->posting('pinjaman.piutang'),
                'kredit',
                $jumlah
            ),
        ]);
    }

    public function recordPembayaranKonsinyasi(PembayaranKonsinyasi $payment): void
    {
        if (JurnalUmum::query()
            ->where('referensi_tipe', PembayaranKonsinyasi::class)
            ->where('referensi_id', $payment->id)
            ->exists()) {
            return;
        }

        $jumlah = (float) ($payment->total_bayar ?? 0);
        $tanggal = (string) ($payment->tanggal_bayar ?? now()->toDateString());

        $this->record([
            'tanggal' => $tanggal,
            'nomor_bukti' => $payment->kode_pembayaran,
            'keterangan' => 'Pembayaran konsinyasi ' . $payment->kode_pembayaran,
            'referensi_tipe' => PembayaranKonsinyasi::class,
            'referensi_id' => $payment->id,
            'created_by' => auth()->id(),
        ], [
            $this->akunResolver->line(
                $this->akunResolver->posting('konsinyasi.utang_reseller'),
                'debit',
                $jumlah
            ),
            $this->akunResolver->line(
                $this->akunResolver->posting('konsinyasi.kas'),
                'kredit',
                $jumlah
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array{akun_id:int, akun_kode:string, akun_nama:string, debit:float, kredit:float}
     */
    private function normalizeLine(array $line): array
    {
        $akun = Akun::query()->aktif()->find($line['akun_id'] ?? null);

        if (! $akun) {
            throw new RuntimeException('Setiap baris jurnal wajib menggunakan akun aktif dari master COA.');
        }

        $debit = round((float) ($line['debit'] ?? 0), 2);
        $kredit = round((float) ($line['kredit'] ?? 0), 2);

        if (! is_finite($debit) || ! is_finite($kredit) || $debit < 0 || $kredit < 0) {
            throw new RuntimeException('Nilai debit dan kredit harus berupa nominal positif yang valid.');
        }

        if (($debit > 0) === ($kredit > 0)) {
            throw new RuntimeException('Satu baris jurnal harus memiliki tepat satu sisi: debit atau kredit.');
        }

        return [
            'akun_id' => $akun->id,
            'akun_kode' => $akun->kode_akun,
            'akun_nama' => $akun->nama_akun,
            'debit' => $debit,
            'kredit' => $kredit,
        ];
    }
}
