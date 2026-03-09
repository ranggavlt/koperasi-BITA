<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\KategoriProdukController;
use App\Http\Controllers\KasirController;
use App\Http\Controllers\ResellerController;
use App\Http\Controllers\KonsinyasiReportController;

Route::get('/', function () {
    return view('pages.dashboard');
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

// PRODUK (pakai resource biar binding rapi)
Route::resource('produk', ProdukController::class)->except(['create', 'show']);

// KATEGORI
Route::get('/kategori-produk', [KategoriProdukController::class, 'index'])->name('kategori.index');

// KASIR
Route::get('/kasir', [KasirController::class, 'index'])->name('kasir.index');

// RESELLER (Konsinyasi)
Route::resource('reseller', ResellerController::class)->except(['create', 'show']);

// LAPORAN KONSINYASI (opsional)
Route::get('/laporan-konsinyasi', [KonsinyasiReportController::class, 'index'])
    ->name('konsinyasi.report');