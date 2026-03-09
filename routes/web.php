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






Route::get('/', function () {
    return view('dashboard');
});

Route::prefix('pages')->group(function () {
    Route::view('/tables', 'pages.tables')->name('pages.tables');
    Route::view('/dashboard', 'pages.dashboard')->name('pages.dashboard');
    Route::view('/profile', 'pages.profile')->name('pages.profile');
    Route::view('/billing', 'pages.billing')->name('pages.billing');
    Route::view('/virtual-reality', 'pages.virtual-reality')->name('pages.virtual');
    Route::view('/rtl', 'pages.rtl')->name('pages.rtl');
    Route::view('/signin', 'pages.sign-in')->name('auth.signin');
    Route::view('/signup', 'pages.sign-up')->name('auth.signup');
});

Route::get('/produk', [ProdukController::class, 'index'])->name('produk.index');
Route::post('/produk', [ProdukController::class, 'store'])->name('produk.store');
Route::get('/produk/{id}/edit', [ProdukController::class, 'edit'])->name('produk.edit');
Route::put('/produk/{id}', [ProdukController::class, 'update'])->name('produk.update');
Route::delete('/produk/{id}', [ProdukController::class, 'destroy'])->name('produk.destroy');

Route::get('/kategori-produk', [KategoriProdukController::class, 'index'])->name('kategori.index');


Route::get('/jenis-simpanan', [JenisSimpananController::class, 'index'])->name('jenis-simpanan.index');
Route::post('/jenis-simpanan', [JenisSimpananController::class, 'store'])->name('jenis-simpanan.store');
Route::get('/jenis-simpanan/{id}/edit', [JenisSimpananController::class, 'edit'])->name('jenis-simpanan.edit');
Route::put('/jenis-simpanan/{id}', [JenisSimpananController::class, 'update'])->name('jenis-simpanan.update');
Route::delete('/jenis-simpanan/{id}', [JenisSimpananController::class, 'destroy'])->name('jenis-simpanan.destroy');
Route::resource('jenis-simpanan', JenisSimpananController::class);

Route::get('/karyawan', [KaryawanController::class,'index'])->name('karyawan.index');
Route::get('/karyawan/create', [KaryawanController::class,'create'])->name('karyawan.create');
Route::post('/karyawan', [KaryawanController::class,'store'])->name('karyawan.store');
Route::get('/karyawan/{id}/edit', [KaryawanController::class,'edit'])->name('karyawan.edit');
Route::put('/karyawan/{id}', [KaryawanController::class,'update'])->name('karyawan.update');
Route::delete('/karyawan/{id}', [KaryawanController::class,'destroy'])->name('karyawan.destroy');

Route::get('/jenis-pinjaman', [JenisPinjamanController::class,'index'])->name('jenis-pinjaman.index');
Route::get('/jenis-pinjaman/create', [JenisPinjamanController::class,'create'])->name('jenis-pinjaman.create');
Route::post('/jenis-pinjaman', [JenisPinjamanController::class,'store'])->name('jenis-pinjaman.store');
Route::get('/jenis-pinjaman/{id}/edit', [JenisPinjamanController::class,'edit'])->name('jenis-pinjaman.edit');
Route::put('/jenis-pinjaman/{id}', [JenisPinjamanController::class,'update'])->name('jenis-pinjaman.update');
Route::delete('/jenis-pinjaman/{id}', [JenisPinjamanController::class,'destroy'])->name('jenis-pinjaman.destroy');

Route::get('/dompet-koperasi', [DompetKoperasiController::class,'index'])->name('dompet-koperasi.index');
Route::get('/dompet-koperasi/create', [DompetKoperasiController::class,'create'])->name('dompet-koperasi.create');
Route::post('/dompet-koperasi', [DompetKoperasiController::class,'store'])->name('dompet-koperasi.store');
Route::get('/dompet-koperasi/{id}/edit', [DompetKoperasiController::class,'edit'])->name('dompet-koperasi.edit');
Route::put('/dompet-koperasi/{id}', [DompetKoperasiController::class,'update'])->name('dompet-koperasi.update');
Route::delete('/dompet-koperasi/{id}', [DompetKoperasiController::class,'destroy'])->name('dompet-koperasi.destroy');

Route::get('/simpanan', [SimpananController::class,'index'])->name('simpanan.index');
Route::get('/simpanan/create', [SimpananController::class,'create'])->name('simpanan.create');
Route::post('/simpanan', [SimpananController::class,'store'])->name('simpanan.store');
Route::delete('/simpanan/{id}', [SimpananController::class,'destroy'])->name('simpanan.destroy');

Route::get('/pinjaman', [PinjamanController::class,'index'])->name('pinjaman.index');
Route::get('/pinjaman/create', [PinjamanController::class,'create'])->name('pinjaman.create');
Route::post('/pinjaman', [PinjamanController::class,'store'])->name('pinjaman.store');
Route::delete('/pinjaman/{id}', [PinjamanController::class,'destroy'])->name('pinjaman.destroy');

Route::get('/cicilan-pinjaman', [CicilanPinjamanController::class,'index'])->name('cicilan-pinjaman.index');
Route::get('/cicilan-pinjaman/create', [CicilanPinjamanController::class,'create'])->name('cicilan-pinjaman.create');
Route::post('/cicilan-pinjaman', [CicilanPinjamanController::class,'store'])->name('cicilan-pinjaman.store');
Route::delete('/cicilan-pinjaman/{id}', [CicilanPinjamanController::class,'destroy'])->name('cicilan-pinjaman.destroy');

Route::get('/mutasi-kas', [MutasiKasController::class,'index'])->name('mutasi-kas.index');
Route::get('/mutasi-kas/create', [MutasiKasController::class,'create'])->name('mutasi-kas.create');
Route::post('/mutasi-kas', [MutasiKasController::class,'store'])->name('mutasi-kas.store');
Route::delete('/mutasi-kas/{id}', [MutasiKasController::class,'destroy'])->name('mutasi-kas.destroy');

// Route::get('/kasir', [KasirController::class, 'index'])->name('kasir.index');