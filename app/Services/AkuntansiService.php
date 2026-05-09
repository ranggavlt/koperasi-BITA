<?php

namespace App\Services;

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
    public const AKUN_KAS = ['kode' => '101', 'nama' => 'Kas'];
    public const AKUN_PIUTANG_ANGGOTA = ['kode' => '103', 'nama' => 'Piutang Anggota (Potong Gaji)'];
    public const AKUN_PIUTANG_PINJAMAN = ['kode' => '105', 'nama' => 'Piutang Pinjaman'];
    public const AKUN_HUTANG_RESELLER = ['kode' => '201', 'nama' => 'Hutang Reseller Konsinyasi'];
    public const AKUN_PENDAPATAN = ['kode' => '401', 'nama' => 'Pendapatan Penjualan'];
    public const AKUN_SIMPANAN = ['kode' => '301', 'nama' => 'Simpanan Anggota'];

    /**
     * @param  array<int, array{akun_kode:string, akun_nama:string, debit:float|int, kredit:float|int}>  $lines
     */
    public function record(array $header, array $lines): JurnalUmum
    {
        $totalDebit = round(collect($lines)->sum(fn ($l) => (float) ($l['debit'] ?? 0)), 2);
        $totalKredit = round(collect($lines)->sum(fn ($l) => (float) ($l['kredit'] ?? 0)), 2);

        if ($totalDebit <= 0 || $totalKredit <= 0 || abs($totalDebit - $totalKredit) > 0.01) {
            throw new RuntimeException('Jurnal tidak balance (debit != kredit).');
        }

        return DB::transaction(function () use ($header, $lines): JurnalUmum {
            $jurnal = JurnalUmum::create($header);
            $jurnal->details()->createMany($lines);
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
            ? self::AKUN_PIUTANG_ANGGOTA
            : self::AKUN_KAS;

        $lines = [
            [
                'akun_kode' => $akunDebit['kode'],
                'akun_nama' => $akunDebit['nama'],
                'debit' => $grandTotal,
                'kredit' => 0,
            ],
        ];

        if ($totalSetorKonsinyasi > 0) {
            $lines[] = [
                'akun_kode' => self::AKUN_HUTANG_RESELLER['kode'],
                'akun_nama' => self::AKUN_HUTANG_RESELLER['nama'],
                'debit' => 0,
                'kredit' => $totalSetorKonsinyasi,
            ];
        }

        $lines[] = [
            'akun_kode' => self::AKUN_PENDAPATAN['kode'],
            'akun_nama' => self::AKUN_PENDAPATAN['nama'],
            'debit' => 0,
            'kredit' => $pendapatan,
        ];

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

        $this->record([
            'tanggal' => $tanggal,
            'nomor_bukti' => 'SIMP-' . $simpanan->id,
            'keterangan' => 'Setoran simpanan',
            'referensi_tipe' => Simpanan::class,
            'referensi_id' => $simpanan->id,
            'created_by' => auth()->id(),
        ], [
            [
                'akun_kode' => self::AKUN_KAS['kode'],
                'akun_nama' => self::AKUN_KAS['nama'],
                'debit' => $jumlah,
                'kredit' => 0,
            ],
            [
                'akun_kode' => self::AKUN_SIMPANAN['kode'],
                'akun_nama' => self::AKUN_SIMPANAN['nama'],
                'debit' => 0,
                'kredit' => $jumlah,
            ],
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
            [
                'akun_kode' => self::AKUN_PIUTANG_PINJAMAN['kode'],
                'akun_nama' => self::AKUN_PIUTANG_PINJAMAN['nama'],
                'debit' => $jumlah,
                'kredit' => 0,
            ],
            [
                'akun_kode' => self::AKUN_KAS['kode'],
                'akun_nama' => self::AKUN_KAS['nama'],
                'debit' => 0,
                'kredit' => $jumlah,
            ],
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
            [
                'akun_kode' => self::AKUN_KAS['kode'],
                'akun_nama' => self::AKUN_KAS['nama'],
                'debit' => $jumlah,
                'kredit' => 0,
            ],
            [
                'akun_kode' => self::AKUN_PIUTANG_PINJAMAN['kode'],
                'akun_nama' => self::AKUN_PIUTANG_PINJAMAN['nama'],
                'debit' => 0,
                'kredit' => $jumlah,
            ],
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
            [
                'akun_kode' => self::AKUN_HUTANG_RESELLER['kode'],
                'akun_nama' => self::AKUN_HUTANG_RESELLER['nama'],
                'debit' => $jumlah,
                'kredit' => 0,
            ],
            [
                'akun_kode' => self::AKUN_KAS['kode'],
                'akun_nama' => self::AKUN_KAS['nama'],
                'debit' => 0,
                'kredit' => $jumlah,
            ],
        ]);
    }
}

