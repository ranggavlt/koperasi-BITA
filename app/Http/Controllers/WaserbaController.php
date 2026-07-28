<?php

namespace App\Http\Controllers;

use App\Models\Anggota;
use App\Models\DompetKoperasi;
use App\Models\Karyawan;
use App\Models\Pembayaran;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Models\KategoriProduk;
use App\Services\PosCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WaserbaController extends Controller
{
    public function index(Request $request): View
    {
        $penjualan = Penjualan::query()
            ->with(['anggota.karyawan', 'karyawan', 'details.produk', 'pembayaran.dompet', 'pembayaran.ledger.limit.periodePotongGaji'])
            ->orderByDesc('id')
            ->paginate(10);

        $produk = Produk::query()
            ->with('kategori')
            ->when($request->kategori, function ($q, $kategoriId) {
                return $q->where('kategori_id', $kategoriId);
            })
            ->when($request->search, function ($q, $search) {
                return $q->where('nama_produk', 'like', "%{$search}%")
                         ->orWhere('kode_produk', 'like', "%{$search}%");
            })
            ->where('stok', '>', 0)
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $produkOptions = $produk->map(fn (Produk $item): array => [
            'id' => $item->id,
            'nama_produk' => $item->nama_produk,
            'stok' => $item->stok,
            'harga_jual' => $item->harga_jual,
        ])->values();

        $anggota = Anggota::query()
            ->with(['karyawan', 'limitsPotongGaji.periodePotongGaji', 'limitsPotongGaji.pemakaian'])
            ->aktif()
            ->whereHas('karyawan', fn ($query) => $query->where('status_kerja', Karyawan::STATUS_AKTIF))
            ->orderBy('nomor_anggota')
            ->get();

        $karyawanNonAnggota = Karyawan::query()
            ->where('status_kerja', Karyawan::STATUS_AKTIF)
            ->whereDoesntHave('anggota', fn ($query) => $query->where('status', Anggota::STATUS_AKTIF))
            ->orderBy('nama')
            ->get();

        $dompetKas = DompetKoperasi::query()->with('akun')->kas()->orderBy('nama_dompet')->get();
        $dompetBank = DompetKoperasi::query()->with('akun')->bank()->orderBy('nama_dompet')->get();
        $dompets = DompetKoperasi::query()->with('akun')->orderBy('nama_dompet')->get();
        $kategoris = KategoriProduk::orderBy('nama_kategori')->get();

        return view('pages.waserba.index', compact(
            'penjualan',
            'produk',
            'produkOptions',
            'anggota',
            'karyawanNonAnggota',
            'dompetKas',
            'dompetBank',
            'dompets',
            'kategoris'
        ));
    }

    public function store(Request $request, PosCheckoutService $checkoutService): RedirectResponse
    {
        $validated = $request->validate([
            'tipe_pelanggan' => 'required|in:' . implode(',', [
                Penjualan::TIPE_ANGGOTA,
                Penjualan::TIPE_KARYAWAN,
                Penjualan::TIPE_UMUM,
            ]),
            'anggota_id' => 'nullable|required_if:tipe_pelanggan,' . Penjualan::TIPE_ANGGOTA . '|exists:anggota,id',
            'karyawan_id' => 'nullable|required_if:tipe_pelanggan,' . Penjualan::TIPE_KARYAWAN . '|exists:karyawan,id',
            'metode_pembayaran' => 'required|in:' . implode(',', [
                Pembayaran::METODE_TUNAI,
                Pembayaran::METODE_TRANSFER_BANK,
                Pembayaran::METODE_QRIS,
                Pembayaran::METODE_POTONG_GAJI,
            ]),
            'dompet_id' => 'nullable|required_unless:metode_pembayaran,' . Pembayaran::METODE_POTONG_GAJI . '|exists:dompet_koperasi,id',
            'tanggal_transaksi' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.produk_id' => 'required|exists:produk,id',
            'items.*.jumlah' => 'required|integer|min:1',
            'diskon' => 'nullable|numeric|min:0',
        ], [
            'tipe_pelanggan.required' => 'Tipe pelanggan wajib dipilih.',
            'anggota_id.required_if' => 'Anggota wajib dipilih untuk transaksi Anggota.',
            'karyawan_id.required_if' => 'Karyawan wajib dipilih untuk transaksi Karyawan nonanggota.',
            'dompet_id.required_unless' => 'Dompet penerimaan wajib dipilih untuk pembayaran non-payroll.',
        ]);

        try {
            $penjualan = $checkoutService->checkout($validated, auth()->id());

            return redirect()
                ->route('waserba.index')
                ->with('success', $penjualan->pembayaran?->metode_pembayaran === Pembayaran::METODE_POTONG_GAJI
                    ? 'Transaksi POS Potong Gaji tersimpan sebagai pending payroll.'
                    : 'Transaksi POS non-payroll tersimpan dan kas/bank tercatat.');
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors())
                ->withInput();
        } catch (\Throwable $exception) {
            return back()
                ->withErrors(['checkout' => $exception->getMessage()])
                ->withInput();
        }
    }
}
