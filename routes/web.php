<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\PublicCategoryController;
use App\Http\Controllers\PublicPageController;
use Illuminate\Support\Facades\Route;

Route::controller(PublicPageController::class)->group(function () {
    Route::get('/', 'home')->name('home');
    Route::get('/catalogo', 'catalog')->name('catalog.index');
    Route::get('/promociones', 'promotions')->name('promotions.index');
    Route::get('/informacion', 'information')->name('information');
});

Route::get('/categorias', [PublicCategoryController::class, 'index'])
    ->name('categories.index');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::view('/admin', 'admin.dashboard')
    ->middleware(['auth', 'active'])
    ->name('admin.dashboard');

Route::middleware(['auth', 'active', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('categories/tree', [CategoryController::class, 'tree'])
            ->name('categories.tree');
        Route::patch('categories/{category}/status', [CategoryController::class, 'updateStatus'])
            ->name('categories.status');
        Route::resource('categories', CategoryController::class);
    });
