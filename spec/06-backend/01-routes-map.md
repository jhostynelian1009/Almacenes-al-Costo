# Documento: Mapa de Rutas de Laravel

> **Alineación MVP:** El checkout genera órdenes sin login. `/admin` se protege desde EP-002. Webhook/callback de pasarela se conservan como diseño futuro y no se registran en el MVP.

## 1. Objetivos del Documento
Especificar detalladamente las rutas HTTP de la aplicación en Laravel, indicando sus métodos, URLs, nombres de ruta y los controladores asociados.

## 2. Mapa de Rutas (`routes/web.php`)

### Rutas Públicas (Cliente)
```php
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/catalog', [ProductController::class, 'index'])->name('catalog.index');
Route::get('/product/{slug}', [ProductController::class, 'show'])->name('catalog.show');

// Carrito (Lógica en frontend, pero se definen vistas de renderizado)
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

// Confirmación y Pagos
Route::get('/order/{order_number}/payment', [PaymentController::class, 'showPaymentDetails'])->name('order.payment');
Route::post('/order/{order_number}/payment/process', [PaymentController::class, 'processPayment'])->name('order.payment.process');
Route::post('/order/{order_number}/payment/upload', [PaymentController::class, 'uploadReceipt'])->name('order.payment.upload');
Route::get('/payment/callback/{gateway}', [PaymentController::class, 'handleCallback'])->name('payment.callback');
Route::get('/order/{order_number}/confirmation', [PaymentController::class, 'confirmation'])->name('order.confirmation');
Route::post('/order/{order_number}/register', [CheckoutController::class, 'registerAfterPurchase'])->name('order.register');

// Webhooks Públicos (Excluídos de protección CSRF en el middleware de Laravel)
Route::post('/api/payment/webhook/{gateway}', [PaymentWebhookController::class, 'handleWebhook'])->name('payment.webhook');
```

### Rutas Protegidas (Panel Administrativo - Prefijo `/admin`, Middleware `auth`)
```php
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // CRUDs
    Route::resource('products', AdminProductController::class);
    Route::resource('categories', AdminCategoryController::class);
    Route::resource('inventories', AdminInventoryController::class)->only(['index', 'edit', 'update']);
    Route::resource('promotions', AdminPromotionController::class);
    
    // Gestión de Pedidos y Validación
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{id}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{id}/approve', [AdminOrderController::class, 'approve'])->name('orders.approve');
    Route::post('/orders/{id}/reject', [AdminOrderController::class, 'reject'])->name('orders.reject');
    Route::get('/orders/{id}/receipt', [AdminOrderController::class, 'downloadReceipt'])->name('orders.receipt.download');
    
    // Configuración y Otros
    Route::get('/settings', [AdminSettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [AdminSettingController::class, 'update'])->name('settings.update');
});
```

## 3. Referencias y Dependencias
*   [06-backend/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/06-backend/README.md)
*   [06-backend/02-controllers.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/06-backend/02-controllers.md)
