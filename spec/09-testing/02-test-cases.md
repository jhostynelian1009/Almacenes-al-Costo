# Documento: Casos de Prueba Detallados

## 1. Objetivos del Documento
Definir los escenarios exactos de prueba de aceptación y técnicos que el sistema debe aprobar antes de pasar a producción.

## 2. Escenarios de Prueba

### Test Case 01: Creación de Pedido (Checkout Inteligente)
*   **Acción**: Agregar 2 productos al carrito, ir a Checkout, llenar el formulario de datos, elegir cualquier método de pago y dar clic en "Generar Pedido".
*   **Resultado Esperado**:
    1. Se crea la orden en la base de datos MySQL con estado `pending`.
    2. Se redirecciona al cliente a `/order/{order_number}/payment`.
    3. El carrito local en `localStorage` se vacía automáticamente solo tras la confirmación exitosa de creación.

### Test Case 02: Carga de Archivo Malicioso en Comprobante (Pago Manual)
*   **Acción**: En la pantalla de carga del comprobante (para pago manual), seleccionar un archivo con extensión `.php` o cambiar la extensión de un script a `.jpg` e intentar subirlo.
*   **Resultado Esperado**:
    1. La validación del tipo MIME real en el backend intercepta el archivo.
    2. La petición es rechazada, no se guarda ningún archivo en `storage/app/comprobantes` y se muestra un error al cliente.

### Test Case 03: Pago Exitoso con Tarjeta (Pasarela y Webhook)
*   **Acción**: Seleccionar método de pago "Tarjeta" e iniciar transacción. Simular una respuesta exitosa de la pasarela enviando un POST válido al Webhook con firma válida.
*   **Resultado Esperado**:
    1. El stock de los productos se bloquea temporalmente por 15 minutos en inventario.
    2. Se registra el webhook en `webhook_logs` y la transacción en `payment_transactions`.
    3. El estado del pago en `payments` cambia a `completed`.
    4. El estado del pedido en `orders` cambia automáticamente a `PAGADO`.
    5. Las existencias del stock se descuentan físicamente de forma permanente.

### Test Case 04: Transacción Fallida en Pasarela (Liberación de Stock)
*   **Acción**: Iniciar un pago por Tarjeta y simular error en cobro (rechazo de fondos o expiración del tiempo límite de 15 minutos).
*   **Resultado Esperado**:
    1. Se recibe notificación de error en transacción.
    2. El estado del pago se actualiza a `failed`.
    3. El stock reservado temporalmente se libera y regresa al inventario físico disponible.
    4. El cliente es redirigido a `/client/payment/failed` y se le permite intentar el cobro nuevamente.

### Test Case 05: Idempotencia de Webhook (Evitar Reprocesamiento)
*   **Acción**: Enviar una petición HTTP POST duplicada al endpoint del Webhook `/api/payment/webhook/{gateway}` con un `event_id` que ya fue procesado en el Test Case 03.
*   **Resultado Esperado**:
    1. El controlador `PaymentWebhookController` detecta la presencia del `event_id` en `webhook_logs`.
    2. El sistema detiene inmediatamente el procesamiento lógico de la orden.
    3. El sistema responde al servidor de la pasarela con un código de estado `200 OK` para evitar bucles, asegurando que no haya deducción de stock duplicada ni envíos duplicados de emails.

## 3. Referencias y Dependencias
*   [09-testing/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/09-testing/README.md)
*   [08-security/02-upload-security.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/08-security/02-upload-security.md)

