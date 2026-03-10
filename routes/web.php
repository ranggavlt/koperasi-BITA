<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\KategoriProdukController;
use App\Http\Controllers\PenjualanController;
use App\Http\Controllers\ResellerController;
use App\Http\Controllers\KonsinyasiReportController;
use App\Http\Controllers\KaryawanController;
use App\Http\Controllers\DashboardController;

Route::get('/', [DashboardController::class, 'index']);

Route::prefix('pages')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('pages.dashboard');
    // yang lain boleh Route::view
    Route::view('/tables', 'pages.tables')->name('pages.tables');
    Route::view('/profile', 'pages.profile')->name('pages.profile');
    Route::view('/billing', 'pages.billing')->name('pages.billing');
    Route::view('/virtual-reality', 'pages.virtual-reality')->name('pages.virtual');
    Route::view('/rtl', 'pages.rtl')->name('pages.rtl');
    Route::view('/signin', 'pages.sign-in')->name('auth.signin');
    Route::view('/signup', 'pages.sign-up')->name('auth.signup');
});

// PRODUK (pakai resource biar binding rapi)
Route::resource('produk', ProdukController::class)->except(['create', 'show']);

// KATEGORI PRODUK
Route::resource('kategori-produk', KategoriProdukController::class)->except(['create', 'show']);

//PENJUALAN
Route::resource('penjualan', PenjualanController::class)->except(['create', 'show']);

// RESELLER (Konsinyasi)
Route::resource('reseller', ResellerController::class)->except(['create', 'show']);

//Karyawan
Route::resource('karyawan', KaryawanController::class)->except(['create', 'show']);



// LAPORAN KONSINYASI (opsional)
Route::get('/laporan-konsinyasi', [KonsinyasiReportController::class, 'index'])
    ->name('konsinyasi.report');


Route::get('/dashboard', [DashboardController::class, 'index'])->name('pages.dashboard');
