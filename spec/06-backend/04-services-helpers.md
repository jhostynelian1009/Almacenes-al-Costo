# Documento: Servicios, Contratos y DTOs del Backend

## 1. Objetivos del Documento
Especificar la responsabilidad de cada clase de servicio, contrato (interfaz), fábrica y DTO del módulo de pagos, garantizando separación de responsabilidades y eliminando duplicidades.

---

## 2. Contrato: `App\Contracts\PaymentGatewayInterface`

**Propósito**: Definir el contrato unificado que todos los adaptadores de pasarela deben cumplir. Es el núcleo del patrón Strategy.

**Responsabilidad única**: Traducir el lenguaje de negocio de *Almacenes al Costo* al lenguaje específico de cada API de pasarela, y viceversa.

**Métodos del contrato**:

| Método | Firma | Responsabilidad |
| :--- | :--- | :--- |
| `initializePayment` | `(Order $order, array $options): PaymentResponse` | Enviar la solicitud de cobro a la pasarela y devolver la URL de redirección o los tokens para el modal. |
| `handleCallback` | `(Request $request): PaymentResponse` | Procesar la respuesta síncrona de retorno del navegador del cliente tras interactuar con el portal de pago. |
| `handleWebhook` | `(Request $request): PaymentResponse` | Procesar y **validar la firma** de la notificación asíncrona servidor-servidor de la pasarela. |
| `validateSignature` | `(Request $request): bool` | Verificar la autenticidad del webhook mediante HMAC o el mecanismo de firma del proveedor. |

> **Regla**: Los adaptadores **no** modifican la base de datos. Solo traducen y retornan un `PaymentResponse`. La persistencia es responsabilidad del `PaymentService`.

---

## 3. Fábrica: `App\Services\Payments\PaymentFactory`

**Propósito**: Centralizar la resolución e instanciación del adaptador correcto basado en el `gateway` solicitado. Separa la lógica de creación del `PaymentService`.

**Responsabilidad única**: Dado un string de gateway (`'stripe'`, `'payphone'`, etc.), devolver la instancia concreta del adaptador correspondiente.

**Método principal**:

*   `make(string $gateway): PaymentGatewayInterface`
    *   Recibe el identificador del gateway.
    *   Inyecta las credenciales necesarias desde `config/services.php` o la tabla `settings`.
    *   Retorna el adaptador concreto: `StripeAdapter`, `PayPhoneAdapter`, `DatafastAdapter`, `KushkiAdapter` o `ManualPaymentAdapter`.
    *   Lanza `\InvalidArgumentException` si el gateway no es reconocido.

---

## 4. Servicio: `App\Services\Payments\PaymentService`

**Propósito**: Orquestar el flujo completo de una transacción de pago. Es el único punto de entrada del sistema para todas las operaciones de cobro.

**Responsabilidad única**: Coordinar la secuencia de operaciones entre el adaptador, la base de datos, y el despacho de eventos. **No** contiene lógica específica de ninguna pasarela.

**Métodos**:

*   `initialize(Order $order, string $gateway, string $paymentMethod, array $options = []): PaymentResponse`
    1.  Usa `PaymentFactory::make($gateway)` para obtener el adaptador.
    2.  Crea el registro en `payments` con `status = pending`.
    3.  Invoca `adapter->initializePayment($order, $options)`.
    4.  Registra la petición/respuesta en `payment_transactions`.
    5.  Si el pago es manual, no hay reserva de stock (el stock se descuenta al aprobar).
    6.  Si es pasarela, reserva el `reserved_stock` en `inventories` durante 15 min.
    7.  Retorna el `PaymentResponse` con la URL de redirección o tokens.

*   `handleCallback(Request $request, string $gateway): PaymentResponse`
    1.  Usa `PaymentFactory::make($gateway)` para obtener el adaptador.
    2.  Invoca `adapter->handleCallback($request)`.
    3.  Registra la respuesta en `payment_transactions` (`event_type = callback_received`).
    4.  **No actualiza estados**: el estado definitivo lo determina el Webhook. El callback solo retorna el estado consultado desde DB para renderizar la vista al cliente.
    5.  Retorna `PaymentResponse` con estado actual.

*   `handleWebhook(Request $request, string $gateway): PaymentResponse`
    1.  Usa `PaymentFactory::make($gateway)` para obtener el adaptador.
    2.  Invoca `adapter->validateSignature($request)`. Si falla, lanza excepción (HTTP 401).
    3.  Extrae el `event_id` del payload.
    4.  Busca en `webhook_logs` por `event_id`. Si ya existe y `processed = true`, retorna `PaymentResponse` sin efectos secundarios (idempotencia).
    5.  Registra el evento en `webhook_logs` (`signature_verified = true`, `processed = false`).
    6.  Invoca `adapter->handleWebhook($request)` para parsear el resultado.
    7.  Registra en `payment_transactions` (`event_type = webhook_received`).
    8.  Si `completed`: actualiza `payment.status = completed`, `order.status = paid`, descuenta `stock` y libera `reserved_stock` definitivamente en `inventories`.
    9.  Si `failed` o `expired`: actualiza `payment.status = failed`, `order.status = canceled`, libera `reserved_stock`.
    10. Despacha el evento de dominio correspondiente (`PaymentCompleted` o `PaymentFailed`).
    11. Marca `webhook_logs.processed = true`.
    12. Retorna `PaymentResponse`.

---

## 5. Adaptadores Concretos

Todos residen en `app/Services/Payments/`. Todos implementan `PaymentGatewayInterface`.

| Clase | Gateway | Notas de implementación |
| :--- | :--- | :--- |
| `StripeAdapter` | Stripe | Usa Stripe PHP SDK. Webhook validado con `Stripe\Webhook::constructEvent()`. |
| `PayPhoneAdapter` | PayPhone | Genera link de cobro. Webhook validado por token Bearer. |
| `DatafastAdapter` | Datafast | API REST con firma por cabecera. |
| `KushkiAdapter` | Kushki | Tokenización de tarjeta en frontend. Cobro por token en servidor. |
| `ManualPaymentAdapter` | manual | No contacta APIs externas. `initializePayment()` retorna `PaymentResponse` con `redirectUrl = null`. `handleWebhook()` y `handleCallback()` no aplican y lanzan `UnsupportedOperationException`. |

---

## 6. Servicio: `App\Services\ReceiptUploadService`

**Propósito**: Encapsular la lógica de carga segura de comprobantes de pago manuales.

**Responsabilidad única**: Gestionar el almacenamiento físico y la vinculación en base de datos del archivo de comprobante.

**Método principal**:

*   `store(UploadedFile $file, Order $order, string $paymentMethod, ?string $reference = null): PaymentReceipt`
    1.  Valida MIME real del archivo (`image/jpeg`, `image/png`, `application/pdf`).
    2.  Valida tamaño máximo (4MB).
    3.  Genera un nombre único con hash: `comprobantes/{order_number}_{hash}.{ext}`.
    4.  Mueve el archivo a `storage/app/comprobantes/` (disco `local`, privado).
    5.  Crea o actualiza el registro en `payment_receipts`.
    6.  Actualiza `order.status = validating`.
    7.  Retorna el modelo `PaymentReceipt`.

---

## 7. DTO: `App\DTOs\PaymentResponse`

**Propósito**: Estandarizar la comunicación entre los adaptadores y el `PaymentService`. Evita que el servicio dependa de estructuras de datos específicas de cada pasarela.

| Atributo | Tipo | Descripción |
| :--- | :--- | :--- |
| `status` | `string` | Estado del cobro: `pending`, `processing`, `completed`, `failed`, `expired`. |
| `transactionId` | `string\|null` | ID externo asignado por la pasarela. |
| `redirectUrl` | `string\|null` | URL de redirección al portal seguro de la pasarela. `null` para pagos manuales. |
| `message` | `string\|null` | Mensaje descriptivo del resultado o del error. |
| `payload` | `array` | Datos brutos completos de la respuesta de la pasarela para logging en `payment_transactions`. |

---

## 8. Referencias y Dependencias
*   [06-backend/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/06-backend/README.md)
*   [03-architecture/01-system-architecture.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/03-architecture/01-system-architecture.md)
*   [08-security/02-upload-security.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/08-security/02-upload-security.md)
*   [05-database/02-schema-definition.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/05-database/02-schema-definition.md)
