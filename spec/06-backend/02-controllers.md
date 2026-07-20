# Documento: Especificación de Controladores y Métodos

> **Alineación MVP:** Checkout genera la orden invitada; Payment gestiona instrucciones/comprobantes; la revisión administrativa es autorizada y auditada. `PaymentWebhookController` es futuro.

## 1. Objetivos del Documento
Definir las responsabilidades, métodos y lógica esperada de cada controlador de Laravel. Los controladores **no contienen lógica de negocio**: delegan en servicios y despachan eventos.

> **Regla de diseño**: Los controladores son responsables únicamente de (1) validar el request HTTP, (2) delegar en el servicio correspondiente, y (3) preparar la respuesta HTTP. La lógica de negocio reside en los servicios.

---

## 2. Controladores Públicos (Cliente)

### `CheckoutController`

*   **`index()`**: Retorna la vista `client.checkout`. Inyecta los métodos de pago disponibles desde la tabla `settings`.
*   **`store(Request $request)`**:
    1.  Valida los datos del cliente (nombre, cédula/RUC, dirección, teléfono, email) y el `cart_data` JSON del carrito.
    2.  Valida que el carrito no esté vacío y que los productos existan y estén activos.
    3.  Genera el `order_number` único (formato: `PED-AAAAMMDD-NNN`).
    4.  Crea la orden en BD con `status = pending_payment`. Registra cada ítem como snapshot en `order_items`.
    5.  Vacía el carrito de `localStorage` (instrucción al frontend vía respuesta JSON).
    6.  Despacha el evento `OrderCreated`.
    7.  Redirecciona a `order.payment` con el `order_number`.

*   **`registerAfterPurchase(Request $request, string $order_number)`**:
    1.  Valida que la orden exista y pertenezca al email del cliente en sesión.
    2.  Valida email único, contraseña y confirmación.
    3.  Crea el registro en `users` con los datos del cliente de la orden.
    4.  Vincula `order.user_id` al nuevo usuario.
    5.  Inicia sesión automáticamente.
    6.  Despacha `CustomerRegistered`.
    7.  Redirecciona a la vista de confirmación o perfil.

---

### `PaymentController`

*   **`showPaymentDetails(string $order_number)`**:
    *   Carga la orden. Si ya tiene `status = paid` o `approved`, redirecciona a confirmación (idempotencia de navegación).
    *   Si el método es manual: inyecta datos bancarios y QR desde `settings`.
    *   Retorna la vista `client.payment`.

*   **`processPayment(Request $request, string $order_number)`**:
    1.  Valida que la orden exista con `status = pending_payment`.
    2.  Valida el método de pago seleccionado.
    3.  Delega en `PaymentService::initialize($order, $gateway, $paymentMethod)`.
    4.  Si el `PaymentResponse::redirectUrl` no es `null` (pago por pasarela): retorna JSON con la URL para que el JS redirige al portal.
    5.  Si es pago manual: retorna la vista con instrucciones bancarias (el `ManualPaymentAdapter` no genera URL).
    *   **Responsabilidad del controlador termina aquí.** La reserva de stock la ejecuta `PaymentService`.

*   **`handleCallback(Request $request, string $gateway)`**:
    1.  Llama a `PaymentService::handleCallback($request, $gateway)`.
    2.  Lee el `order_number` del parámetro de la URL (proveído por la pasarela en la callback URL).
    3.  Consulta el `order.status` actual en BD.
    4.  **No actualiza estados** (eso lo hace el webhook). Solo lee el estado para renderizar la vista correcta al cliente.
    5.  Si `order.status = paid` → renderiza `client.payment.success`.
    6.  Si `order.status = canceled` → renderiza `client.payment.failed`.
    7.  Si `order.status = pending_payment` → renderiza `client.payment.pending` (el webhook puede llegar después).

*   **`uploadReceipt(Request $request, string $order_number)`**:
    1.  Valida que la orden exista con `status = pending_payment`.
    2.  Valida el archivo (MIME, tamaño) — la validación profunda la hace `ReceiptUploadService`.
    3.  Delega en `ReceiptUploadService::store($file, $order, $paymentMethod, $reference)`.
    4.  El servicio actualiza `order.status = validating`.
    5.  Redirecciona a `order.confirmation` con mensaje de espera de revisión.

---

### `PaymentWebhookController`

*   **`handleWebhook(Request $request, string $gateway)`**:
    1.  Delega **inmediatamente** en `PaymentService::handleWebhook($request, $gateway)`.
    2.  No realiza validación propia — toda la lógica reside en el servicio y el adaptador.
    3.  Si el servicio lanza `InvalidSignatureException` → responde `HTTP 401`.
    4.  Si el servicio lanza `InvalidArgumentException` (gateway no reconocido) → responde `HTTP 400`.
    5.  En cualquier otro caso (incluyendo excepciones de procesamiento interno) → responde `HTTP 200 OK`.
    *   **Principio**: El controlador de webhook es deliberadamente delgado. La lógica está en `PaymentService`. Ver especificación completa en [03-architecture/04-webhooks.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/03-architecture/04-webhooks.md).

---

## 3. Controladores Administrativos (`Admin\`)

### `Admin\OrderController`

*   **`index(Request $request)`**: Lista pedidos paginados. Filtra por `status` (`pending_payment`, `validating`, `paid`, `approved`, `preparing`, `canceled`). Filtra por rango de fechas y número de orden.
*   **`show(int $id)`**: Vista de detalle del pedido. Si es pago manual: muestra visor del comprobante y botones de aprobación/rechazo. Si es pasarela: muestra ID de transacción, logs de `payment_transactions` y deshabilita botones de aprobación manual (la pasarela ya lo aprobó).
*   **`downloadReceipt(int $id)`**: Endpoint protegido que retorna el binario del comprobante mediante `Storage::response()`. Solo accesible desde el panel admin.
*   **`approve(int $id)`**:
    1.  Valida que el pedido tenga `status = validating` (solo pagos manuales).
    2.  Actualiza `order.status = approved`.
    3.  Despacha evento `OrderApproved` (el Listener se encarga del descuento de inventario).
*   **`reject(Request $request, int $id)`**:
    1.  Valida que el pedido tenga `status = validating`.
    2.  Valida el campo `reason` (motivo de rechazo, requerido).
    3.  Actualiza `payment_receipts.rejection_reason`.
    4.  Actualiza `order.status = pending_payment` (el cliente puede re-subir comprobante).

### `Admin\ProductController` / `Admin\CategoryController` / `Admin\InventoryController`
*   CRUDs estándar de recursos con paginación, búsqueda y validación de formularios.
*   `InventoryController` solo expone `index`, `edit` y `update` (no crear/eliminar — el inventario se crea automáticamente con el producto).

### `Admin\SettingController`
*   `index()`: Lista configuraciones agrupadas (datos bancarios, QR Deuna, credenciales de pasarelas).
*   `update(Request $request)`: Actualiza la tabla `settings` usando upsert por `key`. Las llaves de pasarelas se validan como no vacías si la pasarela está activa.

---

## 4. Referencias y Dependencias
*   [06-backend/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/06-backend/README.md)
*   [06-backend/01-routes-map.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/06-backend/01-routes-map.md)
*   [06-backend/04-services-helpers.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/06-backend/04-services-helpers.md)
*   [06-backend/05-events.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/06-backend/05-events.md)
*   [03-architecture/04-webhooks.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/03-architecture/04-webhooks.md)
*   [05-database/02-schema-definition.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/05-database/02-schema-definition.md)
