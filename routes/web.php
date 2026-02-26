<?php

use Illuminate\Support\Facades\Route;

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