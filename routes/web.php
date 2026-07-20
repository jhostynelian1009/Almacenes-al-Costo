<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'public.home')->name('home');

// Acceso temporal de previsualización; proteger con autenticación durante la EP-002.
Route::view('/admin', 'admin.dashboard')->name('admin.dashboard');
