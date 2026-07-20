<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\KategoriProdukController;
use App\Http\Controllers\KasirController;
use App\Http\Controllers\JenisSimpananController;
use App\Http\Controllers\JadwalSimpananWajibController;
use App\Http\Controllers\KaryawanController;
use App\Http\Controllers\JenisPinjamanController;
use App\Http\Controllers\DompetKoperasiController;
use App\Http\Controllers\SimpananController;
use App\Http\Controllers\PinjamanController;
use App\Http\Controllers\CicilanPinjamanController;
use App\Http\Controllers\FinanceSewaMobilController;
use App\Http\Controllers\FinanceSewaPrinterController;
use App\Http\Controllers\FinanceBebanOperasionalController;
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
use App\Http\Controllers\UserController;
use App\Http\Controllers\AnggotaController;
use App\Http\Controllers\AsetMobilController;
use App\Http\Controllers\AsetPrinterController;
use App\Http\Controllers\PeriodePotongGajiController;
use App\Http\Controllers\OutstandingCashController;
use App\Http\Controllers\RekonsiliasiPotongGajiController;
use App\Http\Controllers\ReversalTransaksiController;
use App\Http\Controllers\PenyelesaianKeanggotaanController;

Route::get('/', fn () => redirect()->route('pages.dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    // Register routes removed per requirement
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::middleware(['auth', 'active_user'])->group(function () {
    Route::get('/password/change', [AuthController::class, 'showChangePassword'])->name('password.change');
    Route::post('/password/change', [AuthController::class, 'updatePassword'])->name('password.update');
});

Route::middleware(['auth', 'active_user', 'password_changed'])->prefix('pages')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('pages.dashboard');
    // yang lain boleh Route::view
    Route::view('/tables', 'pages.tables')->name('pages.tables');
    Route::view('/profile', 'pages.profile')->name('pages.profile');
    Route::view('/billing', 'pages.billing')->name('pages.billing');
    Route::view('/virtual-reality', 'pages.virtual-reality')->name('pages.virtual');
    Route::view('/rtl', 'pages.rtl')->name('pages.rtl');
});

Route::middleware(['auth', 'active_user', 'password_changed', 'role:kasir,admin'])->group(function () {
    //PENJUALAN (Kasir & Admin)
    Route::resource('penjualan', PenjualanController::class)->only(['index', 'store']);
});

Route::middleware(['auth', 'active_user', 'password_changed', 'role:admin'])->group(function () {
    // MANAJEMEN USER (Hak Akses Login)
    Route::resource('users', UserController::class)->except(['create', 'show', 'edit', 'destroy']);
    Route::patch('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
    Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');

    // PRODUK (pakai resource biar binding rapi)
    Route::resource('produk', ProdukController::class)->except(['create', 'show']);

    // KATEGORI PRODUK
    Route::resource('kategori-produk', KategoriProdukController::class)->except(['create', 'show']);

    // RESELLER (Konsinyasi)
    Route::resource('reseller', ResellerController::class)->except(['create', 'show']);

    Route::resource('jenis-simpanan', JenisSimpananController::class)->except(['show']);

    Route::resource('jenis-pinjaman', JenisPinjamanController::class)->except(['create', 'show']);

    Route::resource('dompet-koperasi', DompetKoperasiController::class)->except(['create', 'show']);

    Route::get('/aset-mobil', [AsetMobilController::class, 'index'])->name('aset-mobil.index');
    Route::post('/aset-mobil', [AsetMobilController::class, 'store'])->name('aset-mobil.store');
    Route::get('/aset-mobil/{aset}/edit', [AsetMobilController::class, 'edit'])->name('aset-mobil.edit');
    Route::put('/aset-mobil/{aset}', [AsetMobilController::class, 'update'])->name('aset-mobil.update');
    Route::patch('/aset-mobil/{aset}/status', [AsetMobilController::class, 'updateStatus'])->name('aset-mobil.status');
    Route::patch('/aset-mobil/{aset}/nonaktifkan', [AsetMobilController::class, 'nonaktifkan'])->name('aset-mobil.nonaktifkan');
    Route::patch('/aset-mobil/{aset}/aktifkan', [AsetMobilController::class, 'aktifkan'])->name('aset-mobil.aktifkan');
    Route::delete('/aset-mobil/{aset}', [AsetMobilController::class, 'destroy'])->name('aset-mobil.destroy');

    Route::middleware('feature:master_printer_enabled')->group(function (): void {
        Route::get('/aset-printer', [AsetPrinterController::class, 'index'])->name('aset-printer.index');
        Route::post('/aset-printer', [AsetPrinterController::class, 'store'])->name('aset-printer.store');
        Route::get('/aset-printer/{aset}/edit', [AsetPrinterController::class, 'edit'])->name('aset-printer.edit');
        Route::put('/aset-printer/{aset}', [AsetPrinterController::class, 'update'])->name('aset-printer.update');
        Route::patch('/aset-printer/{aset}/status', [AsetPrinterController::class, 'updateStatus'])->name('aset-printer.status');
        Route::patch('/aset-printer/{aset}/nonaktifkan', [AsetPrinterController::class, 'nonaktifkan'])->name('aset-printer.nonaktifkan');
        Route::patch('/aset-printer/{aset}/aktifkan', [AsetPrinterController::class, 'aktifkan'])->name('aset-printer.aktifkan');
        Route::delete('/aset-printer/{aset}', [AsetPrinterController::class, 'destroy'])->name('aset-printer.destroy');
    });

    Route::resource('pengurus-koperasi', PengurusKoperasiController::class)
        ->only(['index', 'store', 'edit', 'update']);
    Route::patch('/pengurus-koperasi/{pengurusKoperasi}/nonaktifkan', [PengurusKoperasiController::class, 'deactivate'])
        ->name('pengurus-koperasi.deactivate');
    Route::patch('/pengurus-koperasi/{pengurusKoperasi}/aktifkan', [PengurusKoperasiController::class, 'activate'])
        ->name('pengurus-koperasi.activate');

    Route::get('/simpanan/saldo-sukarela/{anggota}', [SimpananController::class, 'saldoSukarela'])
        ->name('simpanan.saldo-sukarela');
    Route::resource('simpanan', SimpananController::class)->only(['index', 'create', 'store']);
    Route::get('/jadwal-simpanan-wajib', [JadwalSimpananWajibController::class, 'index'])
        ->name('jadwal-simpanan-wajib.index');

    Route::resource('pinjaman', PinjamanController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::post('/pinjaman/{pinjaman}/ajukan', [PinjamanController::class, 'submit'])
        ->name('pinjaman.submit');
    Route::post('/pinjaman/{pinjaman}/setujui', [PinjamanController::class, 'approve'])
        ->name('pinjaman.approve');
    Route::post('/pinjaman/{pinjaman}/tolak', [PinjamanController::class, 'reject'])
        ->name('pinjaman.reject');
    Route::post('/pinjaman/{pinjaman}/batalkan', [PinjamanController::class, 'cancel'])
        ->name('pinjaman.cancel');
    Route::post('/pinjaman/{pinjaman}/cairkan', [PinjamanController::class, 'disburse'])
        ->name('pinjaman.disburse');
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

    // Master data Karyawan dan Anggota
    Route::resource('karyawan', KaryawanController::class)->except(['create', 'show']);
    Route::post('/karyawan/{karyawan}/akun', [KaryawanController::class, 'createAccount'])->name('karyawan.akun.store');
    Route::patch('/karyawan/{karyawan}/akun/password', [KaryawanController::class, 'resetAccountPassword'])->name('karyawan.akun.password');
    Route::patch('/karyawan/{karyawan}/akun/aktifkan', [KaryawanController::class, 'activateAccount'])->name('karyawan.akun.activate');
    Route::patch('/karyawan/{karyawan}/akun/nonaktifkan', [KaryawanController::class, 'deactivateAccount'])->name('karyawan.akun.deactivate');

    Route::resource('anggota', AnggotaController::class)
        ->parameters(['anggota' => 'anggota'])
        ->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::patch('/anggota/{anggota}/nonaktifkan', [AnggotaController::class, 'deactivate'])
        ->name('anggota.deactivate');
    Route::patch('/anggota/{anggota}/aktifkan', [AnggotaController::class, 'activate'])
        ->name('anggota.activate');

    // SHU sementara ditunda menunggu keputusan RAT/client. Route tetap ada,
    // tetapi middleware feature mengembalikan 404 saat flag nonaktif.
    Route::middleware('feature:shu_enabled')->group(function (): void {
        Route::get('/shu-koperasi', [ShuKoperasiController::class, 'index'])->name('shu-koperasi.index');
        Route::post('/shu-koperasi', [ShuKoperasiController::class, 'store'])->name('shu-koperasi.store');
        Route::get('/shu-koperasi/{shuKoperasi}', [ShuKoperasiController::class, 'show'])->name('shu-koperasi.show');
        Route::put('/shu-koperasi/{shuKoperasi}', [ShuKoperasiController::class, 'update'])->name('shu-koperasi.update');
        Route::delete('/shu-koperasi/{shuKoperasi}', [ShuKoperasiController::class, 'destroy'])->name('shu-koperasi.destroy');
        Route::post('/shu-koperasi/{shuKoperasi}/refresh', [ShuKoperasiController::class, 'refresh'])->name('shu-koperasi.refresh');
        Route::post('/shu-koperasi/{shuKoperasi}/transaksi', [ShuKoperasiController::class, 'storeTransaksi'])->name('shu-koperasi.transaksi.store');
        Route::delete('/shu-koperasi/{shuKoperasi}/transaksi/{shuTransaksi}', [ShuKoperasiController::class, 'destroyTransaksi'])->name('shu-koperasi.transaksi.destroy');
    });

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

    Route::get('/penyelesaian-keanggotaan', [PenyelesaianKeanggotaanController::class, 'index'])
        ->name('penyelesaian-keanggotaan.index');
    Route::get('/penyelesaian-keanggotaan/{penyelesaian}', [PenyelesaianKeanggotaanController::class, 'show'])
        ->name('penyelesaian-keanggotaan.show');
    Route::post('/penyelesaian-keanggotaan/{penyelesaian}/refresh', [PenyelesaianKeanggotaanController::class, 'refresh'])
        ->name('penyelesaian-keanggotaan.refresh');
    Route::post('/penyelesaian-keanggotaan/{penyelesaian}/process-offset', [PenyelesaianKeanggotaanController::class, 'processOffset'])
        ->name('penyelesaian-keanggotaan.process-offset');
    Route::post('/penyelesaian-keanggotaan/{penyelesaian}/refund', [PenyelesaianKeanggotaanController::class, 'refund'])
        ->name('penyelesaian-keanggotaan.refund');
    Route::post('/penyelesaian-keanggotaan/{penyelesaian}/complete', [PenyelesaianKeanggotaanController::class, 'complete'])
        ->name('penyelesaian-keanggotaan.complete');
    Route::post('/penyelesaian-keanggotaan/{penyelesaian}/cancel-deactivation', [PenyelesaianKeanggotaanController::class, 'cancelDeactivation'])
        ->name('penyelesaian-keanggotaan.cancel-deactivation');
    Route::post('/penyelesaian-keanggotaan/{penyelesaian}/re-register', [PenyelesaianKeanggotaanController::class, 'reRegister'])
        ->name('penyelesaian-keanggotaan.re-register');

    Route::get('/sewa-mobil', [FinanceSewaMobilController::class, 'index'])
        ->name('sewa-mobil.finance.index');
    Route::get('/sewa-mobil/create', [FinanceSewaMobilController::class, 'create'])
        ->name('sewa-mobil.finance.create');
    Route::post('/sewa-mobil', [FinanceSewaMobilController::class, 'store'])
        ->name('sewa-mobil.finance.store');
    Route::get('/sewa-mobil/{sewaMobil}/edit', [FinanceSewaMobilController::class, 'edit'])
        ->name('sewa-mobil.finance.edit');
    Route::put('/sewa-mobil/{sewaMobil}', [FinanceSewaMobilController::class, 'update'])
        ->name('sewa-mobil.finance.update');
    Route::post('/sewa-mobil/{sewaMobil}/submit', [FinanceSewaMobilController::class, 'submit'])
        ->name('sewa-mobil.finance.submit');
    Route::post('/sewa-mobil/{sewaMobil}/approve', [FinanceSewaMobilController::class, 'approve'])
        ->name('sewa-mobil.finance.approve');
    Route::post('/sewa-mobil/{sewaMobil}/reject', [FinanceSewaMobilController::class, 'reject'])
        ->name('sewa-mobil.finance.reject');
    Route::post('/sewa-mobil/{sewaMobil}/pay', [FinanceSewaMobilController::class, 'pay'])
        ->name('sewa-mobil.finance.pay');
    Route::post('/sewa-mobil/{sewaMobil}/start', [FinanceSewaMobilController::class, 'start'])
        ->name('sewa-mobil.finance.start');
    Route::post('/sewa-mobil/{sewaMobil}/complete', [FinanceSewaMobilController::class, 'complete'])
        ->name('sewa-mobil.finance.complete');
    Route::post('/sewa-mobil/{sewaMobil}/cancel', [FinanceSewaMobilController::class, 'cancel'])
        ->name('sewa-mobil.finance.cancel');

    Route::get('/sewa-printer', [FinanceSewaPrinterController::class, 'index'])
        ->name('sewa-printer.index');
    Route::get('/sewa-printer/create', [FinanceSewaPrinterController::class, 'create'])
        ->name('sewa-printer.create');
    Route::post('/sewa-printer', [FinanceSewaPrinterController::class, 'store'])
        ->name('sewa-printer.store');
    Route::get('/sewa-printer/{sewaPrinter}/edit', [FinanceSewaPrinterController::class, 'edit'])
        ->name('sewa-printer.edit');
    Route::put('/sewa-printer/{sewaPrinter}', [FinanceSewaPrinterController::class, 'update'])
        ->name('sewa-printer.update');
    Route::post('/sewa-printer/{sewaPrinter}/confirm', [FinanceSewaPrinterController::class, 'confirm'])
        ->name('sewa-printer.confirm');
    Route::post('/sewa-printer/{sewaPrinter}/pay', [FinanceSewaPrinterController::class, 'pay'])
        ->name('sewa-printer.pay');
    Route::post('/sewa-printer/{sewaPrinter}/start', [FinanceSewaPrinterController::class, 'start'])
        ->name('sewa-printer.start');
    Route::post('/sewa-printer/{sewaPrinter}/complete', [FinanceSewaPrinterController::class, 'complete'])
        ->name('sewa-printer.complete');
    Route::post('/sewa-printer/{sewaPrinter}/cancel', [FinanceSewaPrinterController::class, 'cancel'])
        ->name('sewa-printer.cancel');

    Route::get('/beban-operasional', [FinanceBebanOperasionalController::class, 'index'])
        ->name('beban-operasional.index');
    Route::get('/beban-operasional/create', [FinanceBebanOperasionalController::class, 'create'])
        ->name('beban-operasional.create');
    Route::post('/beban-operasional', [FinanceBebanOperasionalController::class, 'store'])
        ->name('beban-operasional.store');
    Route::get('/beban-operasional/{bebanOperasional}/edit', [FinanceBebanOperasionalController::class, 'edit'])
        ->name('beban-operasional.edit');
    Route::put('/beban-operasional/{bebanOperasional}', [FinanceBebanOperasionalController::class, 'update'])
        ->name('beban-operasional.update');
    Route::post('/beban-operasional/{bebanOperasional}/post', [FinanceBebanOperasionalController::class, 'post'])
        ->name('beban-operasional.post');
    Route::post('/beban-operasional/{bebanOperasional}/cancel-draft', [FinanceBebanOperasionalController::class, 'cancelDraft'])
        ->name('beban-operasional.cancel-draft');
    Route::post('/beban-operasional/{bebanOperasional}/reverse', [FinanceBebanOperasionalController::class, 'reverse'])
        ->name('beban-operasional.reverse');

    // Akuntansi (Keuangan)
    Route::get('/akun', [AkunController::class, 'index'])->name('akun.index');
    Route::post('/akun', [AkunController::class, 'store'])->name('akun.store');
    Route::patch('/akun/{akun}/beban-operasional-eligibility', [AkunController::class, 'updateBebanOperasionalEligibility'])
        ->name('akun.beban-operasional-eligibility');
    Route::get('/akuntansi/jurnal-umum', [JurnalUmumPeriodikController::class, 'index'])->name('akuntansi.jurnal-umum');
    Route::get('/akuntansi/buku-besar', [BukuBesarController::class, 'index'])->name('akuntansi.buku-besar');
});

Route::middleware(['auth', 'active_user', 'password_changed', 'role:kasir,admin'])->group(function () {
    // LAPORAN KONSINYASI (operasional, boleh kasir & keuangan)
    Route::get('/laporan-konsinyasi', [KonsinyasiReportController::class, 'index'])
        ->name('konsinyasi.report');
    Route::get('/pembayaran-konsinyasi', [PembayaranKonsinyasiController::class, 'index'])
        ->name('pembayaran-konsinyasi.index');
    Route::post('/pembayaran-konsinyasi', [PembayaranKonsinyasiController::class, 'store'])
        ->name('pembayaran-konsinyasi.store');
});
