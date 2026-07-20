# Documento: Estructura Física y Mapeo en Laravel

## 1. Objetivos del Documento
Describir la organización física de archivos y directorios en el repositorio de Laravel del proyecto **Almacenes al Costo**, sirviendo como mapa de referencia para el agente de implementación (Codex).

---

## 2. Mapa de Directorios Clave

```
app/
├── Contracts/
│   └── PaymentGatewayInterface.php      # Interfaz del patrón Strategy
├── DTOs/
│   └── PaymentResponse.php              # DTO de respuesta unificada de pasarelas
├── Events/
│   ├── OrderCreated.php
│   ├── PaymentInitialized.php
│   ├── PaymentCompleted.php
│   ├── PaymentFailed.php
│   ├── OrderApproved.php
│   ├── InventoryUpdated.php
│   └── CustomerRegistered.php
├── Exceptions/
│   ├── InvalidSignatureException.php    # Firma de webhook inválida
│   └── UnsupportedOperationException.php # Operación no soportada por adaptador
├── Http/
│   ├── Controllers/
│   │   ├── HomeController.php
│   │   ├── ProductController.php
│   │   ├── CartController.php
│   │   ├── CheckoutController.php
│   │   ├── PaymentController.php
│   │   ├── PaymentWebhookController.php
│   │   └── Admin/
│   │       ├── DashboardController.php
│   │       ├── ProductController.php
│   │       ├── CategoryController.php
│   │       ├── InventoryController.php
│   │       ├── OrderController.php
│   │       ├── PromotionController.php
│   │       └── SettingController.php
│   └── Middleware/
│       └── VerifyCsrfToken.php          # Excluir rutas /api/payment/webhook/*
├── Jobs/
│   ├── SendOrderReceivedEmail.php
│   ├── SendPaymentConfirmationEmail.php
│   ├── SendPaymentFailedEmail.php
│   ├── SendOrderApprovalEmail.php
│   ├── SendWelcomeEmail.php
│   ├── UpdateInventoryOnPayment.php
│   ├── UpdateInventoryOnApproval.php
│   └── ReleaseReservedStock.php
├── Listeners/
│   ├── SendOrderReceivedEmailListener.php
│   ├── SendPaymentConfirmationEmailListener.php
│   ├── SendPaymentFailedEmailListener.php
│   ├── SendOrderApprovalEmailListener.php
│   ├── SendWelcomeEmailListener.php
│   ├── UpdateInventoryOnPayment.php
│   ├── UpdateInventoryOnApproval.php
│   ├── ReleaseReservedStock.php
│   └── CheckLowStockThreshold.php
├── Models/
│   ├── User.php
│   ├── Category.php
│   ├── Product.php
│   ├── Inventory.php
│   ├── Promotion.php
│   ├── Order.php
│   ├── OrderItem.php
│   ├── Payment.php
│   ├── PaymentTransaction.php
│   ├── PaymentReceipt.php
│   ├── WebhookLog.php
│   └── Setting.php
└── Services/
    ├── Payments/
    │   ├── PaymentService.php           # Orquestador central
    │   ├── PaymentFactory.php           # Resuelve el adaptador según gateway
    │   ├── StripeAdapter.php
    │   ├── PayPhoneAdapter.php
    │   ├── DatafastAdapter.php
    │   ├── KushkiAdapter.php
    │   └── ManualPaymentAdapter.php
    └── ReceiptUploadService.php

database/
├── migrations/
│   ├── ..._create_users_table.php
│   ├── ..._create_categories_table.php
│   ├── ..._create_products_table.php
│   ├── ..._create_inventories_table.php
│   ├── ..._create_promotions_table.php
│   ├── ..._create_orders_table.php
│   ├── ..._create_order_items_table.php
│   ├── ..._create_payments_table.php
│   ├── ..._create_payment_transactions_table.php
│   ├── ..._create_payment_receipts_table.php
│   ├── ..._create_webhook_logs_table.php
│   └── ..._create_settings_table.php
└── seeders/
    ├── DatabaseSeeder.php
    ├── UserSeeder.php
    ├── CategorySeeder.php
    ├── ProductSeeder.php
    └── SettingSeeder.php

resources/
└── views/
    ├── layouts/
    │   ├── app.blade.php               # Layout público (cliente)
    │   └── admin.blade.php             # Layout administrativo
    ├── client/
    │   ├── home.blade.php
    │   ├── catalog.blade.php
    │   ├── cart.blade.php
    │   ├── checkout.blade.php
    │   ├── payment.blade.php           # Pantalla puente (manual: instrucciones / tarjeta: redirección)
    │   ├── confirmation.blade.php      # Confirmación pedidos manuales (status: validating)
    │   └── payment/
    │       ├── success.blade.php       # Pago exitoso por pasarela
    │       ├── failed.blade.php        # Pago fallido o rechazado
    │       └── pending.blade.php       # Pago en proceso (esperando webhook)
    └── admin/
        ├── dashboard/
        ├── products/
        ├── categories/
        ├── inventories/
        ├── orders/
        │   ├── index.blade.php
        │   ├── show.blade.php          # Vista diferenciada: manual vs pasarela
        │   └── partials/
        │       ├── receipt-viewer.blade.php
        │       └── payment-logs.blade.php
        ├── promotions/
        └── settings/

public/
└── js/
    ├── cart.js                         # Gestión de carrito en localStorage
    └── checkout.js                     # Prevención de doble submit + integración pasarela

storage/
└── app/
    └── comprobantes/                   # Directorio privado para comprobantes de pago
```

---

## 3. Notas de Implementación

*   **`app/Console/Kernel.php`**: Registrar las tareas programadas (`ExpirePaymentReservations`, `CleanOldWebhookLogs`, `CleanPaymentTransactions`).
*   **`app/Providers/EventServiceProvider.php`**: Registrar el mapeo completo de Eventos → Listeners.
*   **`config/services.php`**: Registrar las credenciales de las pasarelas leídas desde `.env`.
*   **`routes/web.php`**: Definir todas las rutas según `06-backend/01-routes-map.md`.
*   Las migraciones deben ejecutarse en el orden listado para respetar las dependencias de claves foráneas.

---

## 4. Referencias y Dependencias
*   [03-architecture/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/03-architecture/README.md)
*   [06-backend/04-services-helpers.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/06-backend/04-services-helpers.md)
*   [06-backend/05-events.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/06-backend/05-events.md)
*   [06-backend/06-jobs.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/06-backend/06-jobs.md)
*   [05-database/04-seeders-migrations.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/05-database/04-seeders-migrations.md)
