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
use App\Http\Controllers\AkunController;
use App\Http\Controllers\AnggotaController;
use App\Http\Controllers\PeriodePotongGajiController;
use App\Http\Controllers\OutstandingCashController;
use App\Http\Controllers\RekonsiliasiPotongGajiController;
use App\Http\Controllers\ReversalTransaksiController;

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
    Route::resource('penjualan', PenjualanController::class)->only(['index', 'store']);

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

    Route::resource('pengurus-koperasi', PengurusKoperasiController::class)
        ->only(['index', 'store', 'edit', 'update']);
    Route::patch('/pengurus-koperasi/{pengurusKoperasi}/nonaktifkan', [PengurusKoperasiController::class, 'deactivate'])
        ->name('pengurus-koperasi.deactivate');
    Route::patch('/pengurus-koperasi/{pengurusKoperasi}/aktifkan', [PengurusKoperasiController::class, 'activate'])
        ->name('pengurus-koperasi.activate');

    Route::resource('simpanan', SimpananController::class)->only(['index', 'store']);

    Route::resource('pinjaman', PinjamanController::class)->only(['index', 'store', 'show']);
    Route::post('/pinjaman/{pinjaman}/bayar-tunai-terjadwal', [PinjamanController::class, 'payCashSchedule'])
        ->name('pinjaman.cash-schedule');
    Route::post('/pinjaman/{pinjaman}/lunasi-tunai', [PinjamanController::class, 'payCashFull'])
        ->name('pinjaman.cash-full');

    Route::resource('cicilan-pinjaman', CicilanPinjamanController::class)->only(['index']);

    Route::get('/periode-potong-gaji', [PeriodePotongGajiController::class, 'index'])
        ->name('periode-potong-gaji.index');
    Route::post('/periode-potong-gaji', [PeriodePotongGajiController::class, 'storePeriode'])
        ->name('periode-potong-gaji.store');
    Route::post('/periode-potong-gaji/limit', [PeriodePotongGajiController::class, 'storeLimit'])
        ->name('periode-potong-gaji.limit.store');
    Route::patch('/periode-potong-gaji/limit/{limit}', [PeriodePotongGajiController::class, 'updateLimit'])
        ->name('periode-potong-gaji.limit.update');
    Route::patch('/periode-potong-gaji/limit/{limit}/aktifkan', [PeriodePotongGajiController::class, 'activate'])
        ->name('periode-potong-gaji.limit.activate');
    Route::patch('/periode-potong-gaji/limit/{limit}/tutup', [PeriodePotongGajiController::class, 'close'])
        ->name('periode-potong-gaji.limit.close');
    Route::patch('/periode-potong-gaji/limit/{limit}/konfirmasi', [PeriodePotongGajiController::class, 'confirm'])
        ->name('periode-potong-gaji.limit.confirm');
    Route::post('/periode-potong-gaji/limit/{limit}/pelunasan-payroll', [PeriodePotongGajiController::class, 'payoffPayroll'])
        ->name('periode-potong-gaji.limit.payoff-payroll');

    Route::get('/mutasi-kas', [MutasiKasController::class,'index'])->name('mutasi-kas.index');
    Route::get('/mutasi-kas/create', [MutasiKasController::class,'create'])->name('mutasi-kas.create');
    Route::post('/mutasi-kas', [MutasiKasController::class,'store'])->name('mutasi-kas.store');

    // Master data Karyawan dan Anggota
    Route::resource('karyawan', KaryawanController::class)->except(['create', 'show']);
    Route::resource('anggota', AnggotaController::class)
        ->parameters(['anggota' => 'anggota'])
        ->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::patch('/anggota/{anggota}/nonaktifkan', [AnggotaController::class, 'deactivate'])
        ->name('anggota.deactivate');
    Route::patch('/anggota/{anggota}/aktifkan', [AnggotaController::class, 'activate'])
        ->name('anggota.activate');

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
    Route::get('/rekonsiliasi-potong-gaji', [RekonsiliasiPotongGajiController::class, 'index'])
        ->name('rekonsiliasi-potong-gaji.index');
    Route::get('/outstanding-cash', [OutstandingCashController::class, 'index'])
        ->name('outstanding-cash.index');
    Route::post('/outstanding-cash/bayar', [OutstandingCashController::class, 'paySource'])
        ->name('outstanding-cash.pay-source');
    Route::post('/outstanding-cash/{anggota}/lunasi', [OutstandingCashController::class, 'payAll'])
        ->name('outstanding-cash.pay-all');
    Route::get('/reversal-transaksi', [ReversalTransaksiController::class, 'index'])
        ->name('reversal-transaksi.index');
    Route::post('/penjualan/{penjualan}/reversal', [ReversalTransaksiController::class, 'refundPos'])
        ->name('penjualan.reversal');
    Route::post('/simpanan/{simpanan}/koreksi', [ReversalTransaksiController::class, 'koreksiSimpanan'])
        ->name('simpanan.koreksi');
    Route::post('/cicilan-pinjaman/{cicilan}/reversal', [ReversalTransaksiController::class, 'reverseCicilan'])
        ->name('cicilan-pinjaman.reversal');

    // Akuntansi (Keuangan)
    Route::get('/akun', [AkunController::class, 'index'])->name('akun.index');
    Route::post('/akun', [AkunController::class, 'store'])->name('akun.store');
    Route::get('/akuntansi/jurnal-umum', [JurnalUmumPeriodikController::class, 'index'])->name('akuntansi.jurnal-umum');
    Route::get('/akuntansi/buku-besar', [BukuBesarController::class, 'index'])->name('akuntansi.buku-besar');
});

Route::middleware(['auth', 'role:kasir,keuangan'])->group(function () {
    // LAPORAN KONSINYASI (operasional, boleh kasir & keuangan)
    Route::get('/laporan-konsinyasi', [KonsinyasiReportController::class, 'index'])
        ->name('konsinyasi.report');
});
