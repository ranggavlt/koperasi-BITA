<?php

namespace App\Http\Controllers;

use App\Models\BebanOperasional;
use App\Models\CicilanPinjaman;
use App\Models\DompetKoperasi;
use App\Models\MutasiKas;
use App\Models\Pembayaran;
use App\Models\PembayaranKonsinyasi;
use App\Models\PembayaranOutstandingCash;
use App\Models\PembayaranSewaMobil;
use App\Models\PembayaranSewaHardware;
use App\Models\PemakaianPotongGaji;
use App\Models\Penjualan;
use App\Models\PenyelesaianKeanggotaan;
use App\Models\Pinjaman;
use App\Models\ReversalTransaksi;
use App\Models\Simpanan;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MutasiKasController extends Controller
{
    public function index(Request $request): View
    {
        $sourceOptions = $this->sourceOptions();
        $timezone = (string) config('app.timezone', 'Asia/Jakarta');
        $today = CarbonImmutable::now($timezone);

        $validated = $request->validate([
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'dompet_id' => ['nullable', 'integer', Rule::exists('dompet_koperasi', 'id')],
            'tipe' => ['nullable', Rule::in(['masuk', 'keluar'])],
            'sumber' => ['nullable', Rule::in(array_keys($sourceOptions))],
        ], [
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'dompet_id.exists' => 'Dompet yang dipilih tidak ditemukan.',
            'tipe.in' => 'Tipe mutasi tidak valid.',
            'sumber.in' => 'Sumber transaksi tidak valid.',
        ]);

        $filters = [
            'tanggal_mulai' => $validated['tanggal_mulai'] ?? $today->startOfMonth()->toDateString(),
            'tanggal_selesai' => $validated['tanggal_selesai'] ?? $today->endOfMonth()->toDateString(),
            'dompet_id' => $validated['dompet_id'] ?? null,
            'tipe' => $validated['tipe'] ?? null,
            'sumber' => $validated['sumber'] ?? null,
        ];

        $query = MutasiKas::query()
            ->with('dompet')
            ->whereDate('tanggal', '>=', $filters['tanggal_mulai'])
            ->whereDate('tanggal', '<=', $filters['tanggal_selesai']);

        if ($filters['dompet_id']) {
            $query->where('dompet_id', $filters['dompet_id']);
        }

        if ($filters['tipe']) {
            $query->where('tipe', $filters['tipe']);
        }

        if ($filters['sumber'] === 'manual') {
            $query->whereNull('referensi_tipe');
        } elseif ($filters['sumber']) {
            $query->where('referensi_tipe', $filters['sumber']);
        }

        $summaryRow = (clone $query)
            ->selectRaw("COALESCE(SUM(CASE WHEN tipe = 'masuk' THEN jumlah ELSE 0 END), 0) as total_masuk")
            ->selectRaw("COALESCE(SUM(CASE WHEN tipe = 'keluar' THEN jumlah ELSE 0 END), 0) as total_keluar")
            ->first();

        $summary = [
            'total_masuk' => $this->rupiahInteger($summaryRow?->total_masuk),
            'total_keluar' => $this->rupiahInteger($summaryRow?->total_keluar),
        ];
        $summary['neto'] = $summary['total_masuk'] - $summary['total_keluar'];

        $data = $query
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $dompetOptions = DompetKoperasi::query()
            ->orderBy('nama_dompet')
            ->get(['id', 'nama_dompet', 'jenis_dompet']);

        return view('pages.mutasi-kas.index', [
            'data' => $data,
            'dompetOptions' => $dompetOptions,
            'filters' => $filters,
            'hasDompet' => $dompetOptions->isNotEmpty(),
            'hasAnyMutasi' => MutasiKas::query()->exists(),
            'sourceOptions' => $sourceOptions,
            'summary' => $summary,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function sourceOptions(): array
    {
        return [
            'manual' => 'Manual',
            Penjualan::class => 'Penjualan POS',
            Pembayaran::class => 'Pembayaran POS/Payroll',
            Simpanan::class => 'Simpanan',
            Pinjaman::class => 'Pinjaman',
            CicilanPinjaman::class => 'Cicilan Pinjaman',
            PemakaianPotongGaji::class => 'Ledger Potong Gaji',
            PembayaranKonsinyasi::class => 'Pembayaran Konsinyasi',
            ReversalTransaksi::class => 'Refund/Reversal',
            PembayaranOutstandingCash::class => 'Tagihan Tunai',
            PembayaranSewaMobil::class => 'Sewa Mobil',
            PembayaranSewaHardware::class => 'Sewa Hardware',
            BebanOperasional::class => 'Beban Operasional',
            PenyelesaianKeanggotaan::class => 'Penyelesaian Keanggotaan',
        ];
    }

    private function rupiahInteger(mixed $value): int
    {
        $value = trim((string) ($value ?? '0'));

        if ($value === '') {
            return 0;
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        $whole = explode('.', $value, 2)[0] ?? '0';
        $whole = preg_replace('/\D/', '', $whole) ?: '0';

        $amount = (int) $whole;

        return $negative ? -1 * $amount : $amount;
    }
}
