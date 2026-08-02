<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\KategoriProdukController;
use App\Http\Controllers\KasirController;


use App\Http\Controllers\KaryawanController;

use App\Http\Controllers\DompetKoperasiController;
use App\Http\Controllers\SimpananController;
use App\Http\Controllers\PinjamanController;
use App\Http\Controllers\CicilanPinjamanController;
use App\Http\Controllers\FinanceSewaMobilController;
use App\Http\Controllers\FinanceSewaHardwareController;
use App\Http\Controllers\FinanceBebanOperasionalController;
use App\Http\Controllers\MutasiKasController;
use App\Http\Controllers\WaserbaController;
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
use App\Http\Controllers\JenisSimpananController;

use App\Http\Controllers\AsetPrinterController;
use App\Http\Controllers\PeriodePotongGajiController;
use App\Http\Controllers\OutstandingCashController;
use App\Http\Controllers\RekonsiliasiPotongGajiController;
use App\Http\Controllers\ReversalTransaksiController;
use App\Http\Controllers\PenyelesaianKeanggotaanController;
use App\Http\Controllers\InvoicePenagihanController;
use App\Http\Controllers\JadwalSimpananWajibController;
use App\Http\Controllers\B2BPaymentController;
use App\Http\Controllers\DanaSosialController;

Route::get('/', fn () => redirect()->route('pages.dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.submit');
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
    //WASERBA (Kasir & Admin)
    Route::resource('waserba', WaserbaController::class)->only(['index', 'store']);
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





    Route::resource('dompet-koperasi', DompetKoperasiController::class)->except(['create', 'show']);



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

    Route::resource('jenis-simpanan', JenisSimpananController::class)
        ->only(['index', 'create', 'store', 'edit', 'update']);

    Route::get('/simpanan/saldo-manasuka/{anggota}', [SimpananController::class, 'saldoManasuka'])
        ->name('simpanan.saldo-manasuka');
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
    Route::get('/periode-potong-gaji/create', [PeriodePotongGajiController::class, 'create'])
        ->name('periode-potong-gaji.create');
    Route::post('/periode-potong-gaji', [PeriodePotongGajiController::class, 'storePeriode'])
        ->name('periode-potong-gaji.store');
    Route::patch('/periode-potong-gaji/kebijakan-limit', [PeriodePotongGajiController::class, 'updateGlobalPolicy'])
        ->name('periode-potong-gaji.kebijakan-limit.update');
    Route::post('/periode-potong-gaji/{periode}/generate-limits', [PeriodePotongGajiController::class, 'bulkGenerate'])
        ->name('periode-potong-gaji.bulk-generate');
    Route::post('/periode-potong-gaji/{periode}/activate-limits', [PeriodePotongGajiController::class, 'bulkActivate'])
        ->name('periode-potong-gaji.bulk-activate');
    Route::post('/periode-potong-gaji/limit', [PeriodePotongGajiController::class, 'storeLimit'])
        ->name('periode-potong-gaji.limit.store');
    Route::patch('/periode-potong-gaji/limit/{limit}', [PeriodePotongGajiController::class, 'updateLimit'])
        ->name('periode-potong-gaji.limit.update');
    Route::post('/periode-potong-gaji/anggota/{anggota}/limit-khusus', [PeriodePotongGajiController::class, 'setOverride'])
        ->name('periode-potong-gaji.anggota.override.store');
    Route::post('/periode-potong-gaji/anggota/{anggota}/limit-khusus/reset', [PeriodePotongGajiController::class, 'resetOverride'])
        ->name('periode-potong-gaji.anggota.override.reset');
    Route::post('/periode-potong-gaji/anggota/{anggota}/kredit-waserba/nonaktifkan', [PeriodePotongGajiController::class, 'disableWaserba'])
        ->name('periode-potong-gaji.anggota.kredit-waserba.disable');
    Route::post('/periode-potong-gaji/anggota/{anggota}/kredit-waserba/aktifkan', [PeriodePotongGajiController::class, 'enableWaserba'])
        ->name('periode-potong-gaji.anggota.kredit-waserba.enable');
    Route::patch('/periode-potong-gaji/limit/{limit}/aktifkan', [PeriodePotongGajiController::class, 'activate'])
        ->name('periode-potong-gaji.limit.activate');
    Route::patch('/periode-potong-gaji/limit/{limit}/tutup', [PeriodePotongGajiController::class, 'close'])
        ->name('periode-potong-gaji.limit.close');
    Route::patch('/periode-potong-gaji/limit/{limit}/konfirmasi', [PeriodePotongGajiController::class, 'confirm'])
        ->name('periode-potong-gaji.limit.confirm');
    Route::post('/periode-potong-gaji/limit/{limit}/pelunasan-payroll', [PeriodePotongGajiController::class, 'payoffPayroll'])
        ->name('periode-potong-gaji.limit.payoff-payroll');

    Route::get('/mutasi-kas', [MutasiKasController::class,'index'])->name('mutasi-kas.index');

    Route::get('/invoice-penagihan', [InvoicePenagihanController::class, 'index'])
        ->name('invoice-penagihan.index');
    Route::post('/invoice-penagihan', [InvoicePenagihanController::class, 'store'])
        ->name('invoice-penagihan.store');
    Route::post('/invoice-penagihan/{invoicePenagihan}/payments', [InvoicePenagihanController::class, 'pay'])
        ->name('invoice-penagihan.pay');

    // KARYAWAN
    Route::get('karyawan/template', [KaryawanController::class, 'downloadTemplate'])->name('karyawan.template');
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
        Route::get('/shu-config', [App\Http\Controllers\ShuConfigController::class, 'index'])->name('shu-config.index');
        Route::post('/shu-config', [App\Http\Controllers\ShuConfigController::class, 'update'])->name('shu-config.update');

        Route::get('/shu-koperasi', [ShuKoperasiController::class, 'index'])->name('shu-koperasi.index');
        Route::post('/shu-koperasi', [ShuKoperasiController::class, 'store'])->name('shu-koperasi.store');
        Route::get('/shu-koperasi/{shuKoperasi}', [ShuKoperasiController::class, 'show'])->name('shu-koperasi.show');
        Route::put('/shu-koperasi/{shuKoperasi}', [ShuKoperasiController::class, 'update'])->name('shu-koperasi.update');
        Route::post('/shu-koperasi/{shuKoperasi}/refresh', [ShuKoperasiController::class, 'refresh'])->name('shu-koperasi.refresh');
        Route::post('/shu-koperasi/{shuKoperasi}/transaksi', [ShuKoperasiController::class, 'storeTransaksi'])->name('shu-koperasi.transaksi.store');
    });

    Route::get('/klaim-dana-sosial', [DanaSosialController::class, 'index'])->name('klaim-dana-sosial.index');
    Route::post('/klaim-dana-sosial/sources', [DanaSosialController::class, 'storeSource'])->name('klaim-dana-sosial.sources.store');
    Route::post('/klaim-dana-sosial/sources/{source}/approve', [DanaSosialController::class, 'approveSource'])->name('klaim-dana-sosial.sources.approve');
    Route::post('/klaim-dana-sosial/claims', [DanaSosialController::class, 'storeClaim'])->name('klaim-dana-sosial.claims.store');
    Route::post('/klaim-dana-sosial/claims/{claim}/submit', [DanaSosialController::class, 'submit'])->name('klaim-dana-sosial.claims.submit');
    Route::post('/klaim-dana-sosial/claims/{claim}/approve', [DanaSosialController::class, 'approve'])->name('klaim-dana-sosial.claims.approve');
    Route::post('/klaim-dana-sosial/claims/{claim}/reject', [DanaSosialController::class, 'reject'])->name('klaim-dana-sosial.claims.reject');
    Route::post('/klaim-dana-sosial/claims/{claim}/pay', [DanaSosialController::class, 'pay'])->name('klaim-dana-sosial.claims.pay');

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
    Route::post('/sewa-mobil/{sewaMobil}/pay-vendor', [B2BPaymentController::class, 'payMobilVendor'])
        ->name('sewa-mobil.finance.pay-vendor');
    Route::post('/sewa-mobil/{sewaMobil}/reject', [FinanceSewaMobilController::class, 'reject'])
        ->name('sewa-mobil.finance.reject');
    Route::post('/sewa-mobil/{sewaMobil}/start', [FinanceSewaMobilController::class, 'start'])
        ->name('sewa-mobil.finance.start');
    Route::post('/sewa-mobil/{sewaMobil}/complete', [FinanceSewaMobilController::class, 'complete'])
        ->name('sewa-mobil.finance.complete');
    Route::post('/sewa-mobil/{sewaMobil}/cancel', [FinanceSewaMobilController::class, 'cancel'])
        ->name('sewa-mobil.finance.cancel');

    Route::get('/sewa-hardware', [FinanceSewaHardwareController::class, 'index'])
        ->name('sewa-hardware.index');
    Route::get('/sewa-hardware/create', [FinanceSewaHardwareController::class, 'create'])
        ->name('sewa-hardware.create');
    Route::post('/sewa-hardware', [FinanceSewaHardwareController::class, 'store'])
        ->name('sewa-hardware.store');
    Route::get('/sewa-hardware/{sewaHardware}/edit', [FinanceSewaHardwareController::class, 'edit'])
        ->name('sewa-hardware.edit');
    Route::put('/sewa-hardware/{sewaHardware}', [FinanceSewaHardwareController::class, 'update'])
        ->name('sewa-hardware.update');
    Route::post('/sewa-hardware/{sewaHardware}/confirm', [FinanceSewaHardwareController::class, 'confirm'])
        ->name('sewa-hardware.confirm');
    Route::post('/sewa-hardware/{sewaHardware}/pay-vendor', [B2BPaymentController::class, 'payHardwareVendor'])
        ->name('sewa-hardware.pay-vendor');
    Route::post('/sewa-hardware/{sewaHardware}/start', [FinanceSewaHardwareController::class, 'start'])
        ->name('sewa-hardware.start');
    Route::post('/sewa-hardware/{sewaHardware}/complete', [FinanceSewaHardwareController::class, 'complete'])
        ->name('sewa-hardware.complete');
    Route::post('/sewa-hardware/{sewaHardware}/cancel', [FinanceSewaHardwareController::class, 'cancel'])
        ->name('sewa-hardware.cancel');
    Route::post('/sewa-hardware/{sewaHardware}/refund', [FinanceSewaHardwareController::class, 'refund'])
        ->name('sewa-hardware.refund');

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
