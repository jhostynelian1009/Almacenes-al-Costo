<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\InventoryAlertController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\InventoryMinimumStockController;
use App\Http\Controllers\Admin\InventoryMovementController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderConfirmationController;
use App\Http\Controllers\PublicCategoryController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\PublicProductController;
use Illuminate\Support\Facades\Route;

Route::controller(PublicPageController::class)->group(function () {
    Route::get('/', 'home')->name('home');
    Route::get('/promociones', 'promotions')->name('promotions.index');
    Route::get('/informacion', 'information')->name('information');
});

Route::get('/catalogo', [PublicProductController::class, 'index'])
    ->name('catalog.index');

Route::get('/product/{product:slug}', [PublicProductController::class, 'show'])
    ->name('catalog.show');

Route::get('/categorias', [PublicCategoryController::class, 'index'])
    ->name('categories.index');

Route::get('/carrito', [CartController::class, 'index'])->name('cart.index');
Route::post('/carrito/items', [CartController::class, 'store'])->name('cart.items.store');
Route::patch('/carrito/items/{product:slug}', [CartController::class, 'update'])->name('cart.items.update');
Route::delete('/carrito/items/{product:slug}', [CartController::class, 'destroy'])->name('cart.items.destroy');
Route::delete('/carrito', [CartController::class, 'clear'])->name('cart.clear');

Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
Route::post('/checkout/review', [CheckoutController::class, 'review'])->name('checkout.review');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

Route::get('/pedido/{orderReference}/confirmacion', [OrderConfirmationController::class, 'show'])
    ->name('orders.confirmation');

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
        Route::get('inventory', [InventoryController::class, 'index'])
            ->name('inventory.index');
        Route::get('inventory/alerts', [InventoryAlertController::class, 'index'])
            ->name('inventory.alerts');
        Route::get('inventory/{inventory}', [InventoryController::class, 'show'])
            ->name('inventory.show');
        Route::patch('inventory/{inventory}/minimum-stock', [InventoryMinimumStockController::class, 'update'])
            ->name('inventory.minimum-stock.update');
        Route::get('inventory/{inventory}/movements/create', [InventoryMovementController::class, 'create'])
            ->name('inventory.movements.create');
        Route::post('inventory/{inventory}/movements', [InventoryMovementController::class, 'store'])
            ->name('inventory.movements.store');
        Route::resource('products', ProductController::class);
        Route::get('categories/tree', [CategoryController::class, 'tree'])
            ->name('categories.tree');
        Route::patch('categories/{category}/status', [CategoryController::class, 'updateStatus'])
            ->name('categories.status');
        Route::resource('categories', CategoryController::class);
    });
