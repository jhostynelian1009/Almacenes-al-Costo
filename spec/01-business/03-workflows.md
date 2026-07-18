# Documento: Flujos de Procesos y Workflows

## 1. Objetivos del Documento
Diagramar y detallar paso a paso los procesos lógicos y operativos de **Almacenes al Costo**, incluyendo el flujo completo del Checkout y el ciclo de validación de pedidos manuales.

---

## 2. Flujo Completo del Checkout

El siguiente diagrama representa el flujo definitivo desde el carrito hasta la confirmación, incluyendo la bifurcación por método de pago.

```mermaid
flowchart TD
    A([Carrito de Compras\n localStorage]) --> B[Ir al Checkout]
    B --> C[Formulario: Datos del Cliente\nNombre, Cédula, Dirección, Teléfono, Email]
    C --> D[Selección del Método de Pago\nTarjeta / Transferencia / Deuna]
    D --> E[POST /checkout\nCheckoutController::store]
    E --> F{Validación\ndel servidor}
    F -- Inválido --> C
    F -- Válido --> G[Crear orden en BD\nstatus: pending_payment\nRegistrar order_items\nSnapshot de precios]
    G --> H{Método elegido}

    H -- Tarjeta --> I[PaymentController::processPayment\nPaymentService::initialize\nAdaptador de pasarela]
    I --> J[Reservar stock temporal\n+15 min en inventories.reserved_stock]
    J --> K[API Pasarela: Crear transacción\nRegistrar payment pending\nRegistrar payment_transaction]
    K --> L[Redirigir al portal\no modal tokenizado]
    L --> M((Cliente paga\nen pasarela segura))
    M --> N{Resultado\ndel cobro}
    N -- Exitoso --> O[Webhook POST\n/api/payment/webhook/gateway\nPaymentWebhookController]
    O --> P[Validar firma HMAC\nVerificar idempotencia\nen webhook_logs]
    P --> Q[payment.status = completed\norder.status = paid\nDescontar stock definitivo]
    Q --> R[GET /payment/callback/gateway\nPaymentController::handleCallback]
    R --> S([Vista: Pago Exitoso\nclient/payment/success.blade.php\nOpción Crear Cuenta])

    N -- Fallido --> T[Webhook o callback de error\npayment.status = failed\norder.status = canceled]
    T --> U[Liberar reserved_stock]
    U --> V([Vista: Pago Fallido\nclient/payment/failed.blade.php\nBotón Reintentar])

    H -- Transferencia\no Deuna --> W[Mostrar datos bancarios\no QR de Deuna]
    W --> X[Cliente sube comprobante\nPOST /order/order_number/payment/upload\nReceiptUploadService]
    X --> Y[Guardar archivo en\nstorage/app/comprobantes\norder.status = validating]
    Y --> Z([Vista: Confirmación Manual\nclient/confirmation.blade.php\nEsperando revisión])
```

---

## 3. Flujo de Validación Manual (Panel Administrativo)

```mermaid
sequenceDiagram
    actor Administrador
    participant Panel as Admin Panel
    participant Laravel as Servidor (Laravel)
    database DB as Base de Datos (MySQL)
    actor Cliente

    Administrador->>Panel: Accede a Pedidos → Filtro: Validando
    Panel->>Laravel: GET /admin/orders?status=validating
    Laravel->>DB: Consulta órdenes con status = validating
    Laravel-->>Panel: Lista de pedidos con comprobante
    Administrador->>Panel: Abre detalle del pedido
    Panel->>Laravel: GET /admin/orders/{id}/receipt (Ruta protegida)
    Laravel-->>Panel: Binario del archivo (Storage::response())
    Administrador->>Administrador: Verifica fondos en banco/Deuna

    alt Fondos confirmados
        Administrador->>Panel: Clic en "Aprobar"
        Panel->>Laravel: POST /admin/orders/{id}/approve
        Laravel->>DB: order.status = approved
        Laravel->>DB: Descontar stock definitivo en inventories
        Laravel-->>Administrador: Confirmación de aprobación
    else Comprobante inválido o fondos incorrectos
        Administrador->>Panel: Clic en "Rechazar" + Motivo
        Panel->>Laravel: POST /admin/orders/{id}/reject (motivo)
        Laravel->>DB: order.status = pending_payment
        Laravel->>DB: payment_receipts.rejection_reason = motivo
        Laravel-->>Administrador: Confirmación de rechazo
    end
```

---

## 4. Referencias y Dependencias
*   [01-business/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/01-business/README.md)
*   [01-business/01-business-rules.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/01-business/01-business-rules.md)
*   [03-architecture/02-integration-diagrams.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/03-architecture/02-integration-diagrams.md)
*   [06-backend/02-controllers.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/06-backend/02-controllers.md)
