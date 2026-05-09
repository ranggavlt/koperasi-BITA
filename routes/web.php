<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\KategoriProdukController;
use App\Http\Controllers\KasirController;
use App\Http\Controllers\JenisSimpananController;
use App\Http\Controllers\KaryawanController;
use App\Http\Controllers\JenisPinjamanController;
use App\Http\Controllers\DompetKoperasiController;
use App\Http\Controllers\SimpananController;
use App\Http\Controllers\PinjamanController;
use App\Http\Controllers\CicilanPinjamanController;
use App\Http\Controllers\MutasiKasController;
use App\Http\Controllers\PenjualanController;
use App\Http\Controllers\ResellerController;
use App\Http\Controllers\PembayaranKonsinyasiController;
use App\Http\Controllers\KonsinyasiReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PengurusKoperasiController;
use App\Http\Controllers\LaporanPotongGajiController;
use App\Http\Controllers\ShuKoperasiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\JurnalUmumPeriodikController;
use App\Http\Controllers\BukuBesarController;

Route::get('/', fn () => redirect()->route('pages.dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.submit');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->prefix('pages')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('pages.dashboard');
    // yang lain boleh Route::view
    Route::view('/tables', 'pages.tables')->name('pages.tables');
    Route::view('/profile', 'pages.profile')->name('pages.profile');
    Route::view('/billing', 'pages.billing')->name('pages.billing');
    Route::view('/virtual-reality', 'pages.virtual-reality')->name('pages.virtual');
    Route::view('/rtl', 'pages.rtl')->name('pages.rtl');
});

Route::middleware(['auth', 'role:kasir'])->group(function () {
    // PRODUK (pakai resource biar binding rapi)
    Route::resource('produk', ProdukController::class)->except(['create', 'show']);

    // KATEGORI PRODUK
    Route::resource('kategori-produk', KategoriProdukController::class)->except(['create', 'show']);

    //PENJUALAN (Kasir)
    Route::resource('penjualan', PenjualanController::class)->except(['create', 'show']);

    // RESELLER (Konsinyasi)
    Route::resource('reseller', ResellerController::class)->except(['create', 'show']);
    Route::get('/pembayaran-konsinyasi', [PembayaranKonsinyasiController::class, 'index'])
        ->name('pembayaran-konsinyasi.index');
    Route::post('/pembayaran-konsinyasi', [PembayaranKonsinyasiController::class, 'store'])
        ->name('pembayaran-konsinyasi.store');
});

Route::middleware(['auth', 'role:keuangan'])->group(function () {
    Route::resource('jenis-simpanan', JenisSimpananController::class)->except(['create', 'show']);

    Route::resource('jenis-pinjaman', JenisPinjamanController::class)->except(['create', 'show']);

    Route::resource('dompet-koperasi', DompetKoperasiController::class)->except(['create', 'show']);

    Route::resource('pengurus-koperasi', PengurusKoperasiController::class)->except(['create', 'show']);

    Route::resource('simpanan', SimpananController::class)->only(['index', 'store', 'destroy']);

    Route::resource('pinjaman', PinjamanController::class)->only(['index', 'store', 'destroy']);

    Route::resource('cicilan-pinjaman', CicilanPinjamanController::class)->only(['index', 'store', 'destroy']);

    Route::get('/mutasi-kas', [MutasiKasController::class,'index'])->name('mutasi-kas.index');
    Route::get('/mutasi-kas/create', [MutasiKasController::class,'create'])->name('mutasi-kas.create');
    Route::post('/mutasi-kas', [MutasiKasController::class,'store'])->name('mutasi-kas.store');
    Route::delete('/mutasi-kas/{id}', [MutasiKasController::class,'destroy'])->name('mutasi-kas.destroy');

    //Karyawan/Anggota
    Route::resource('karyawan', KaryawanController::class)->except(['create', 'show']);

    Route::get('/shu-koperasi', [ShuKoperasiController::class, 'index'])->name('shu-koperasi.index');
    Route::post('/shu-koperasi', [ShuKoperasiController::class, 'store'])->name('shu-koperasi.store');
    Route::get('/shu-koperasi/{shuKoperasi}', [ShuKoperasiController::class, 'show'])->name('shu-koperasi.show');
    Route::put('/shu-koperasi/{shuKoperasi}', [ShuKoperasiController::class, 'update'])->name('shu-koperasi.update');
    Route::delete('/shu-koperasi/{shuKoperasi}', [ShuKoperasiController::class, 'destroy'])->name('shu-koperasi.destroy');
    Route::post('/shu-koperasi/{shuKoperasi}/refresh', [ShuKoperasiController::class, 'refresh'])->name('shu-koperasi.refresh');
    Route::post('/shu-koperasi/{shuKoperasi}/transaksi', [ShuKoperasiController::class, 'storeTransaksi'])->name('shu-koperasi.transaksi.store');
    Route::delete('/shu-koperasi/{shuKoperasi}/transaksi/{shuTransaksi}', [ShuKoperasiController::class, 'destroyTransaksi'])->name('shu-koperasi.transaksi.destroy');

    Route::get('/laporan-potong-gaji', [LaporanPotongGajiController::class, 'index'])
        ->name('laporan.potong-gaji');

    // Akuntansi (Keuangan)
    Route::get('/akuntansi/jurnal-umum', [JurnalUmumPeriodikController::class, 'index'])->name('akuntansi.jurnal-umum');
    Route::get('/akuntansi/buku-besar', [BukuBesarController::class, 'index'])->name('akuntansi.buku-besar');
});

Route::middleware(['auth', 'role:kasir,keuangan'])->group(function () {
    // LAPORAN KONSINYASI (operasional, boleh kasir & keuangan)
    Route::get('/laporan-konsinyasi', [KonsinyasiReportController::class, 'index'])
        ->name('konsinyasi.report');
});
