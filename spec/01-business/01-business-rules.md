# Documento: Reglas de Negocio

## 1. Objetivos del Documento
Establecer las reglas lógicas e inmutables que norman el comportamiento del sistema **Almacenes al Costo** y los ciclos de vida de los dominios clave.

---

## 2. Reglas del Negocio (MVP)

*   **RN-01 — Compra sin cuenta obligatoria**: Un cliente puede buscar, seleccionar y comprar productos sin tener una cuenta registrada previamente en la plataforma.
*   **RN-02 — Métodos de pago aceptados**: El MVP acepta tres métodos durante el Checkout: (1) Tarjeta de crédito/débito vía pasarela, (2) Transferencia bancaria directa, (3) Deuna. El cliente elige libremente.
*   **RN-03 — Validación manual exclusiva para pagos manuales**: La revisión de comprobantes por el Administrador aplica **solo** para Transferencia y Deuna. El pedido pasa a `validating` al subir el comprobante y a `approved` tras confirmación manual de fondos.
*   **RN-04 — Procesamiento automatizado de tarjeta**: Al pagar con Tarjeta, el cobro lo procesa la pasarela. El `PaymentWebhookController` recibe la notificación, valida la firma y actualiza el pedido a `paid` y el pago a `completed`.
*   **RN-05 — Registro opcional post-compra**: El cliente puede opcionalmente registrar una contraseña en la pantalla de confirmación para crear su cuenta, heredando los datos del checkout.
*   **RN-06 — Bloqueo temporal de stock**: Al iniciar una transacción con Tarjeta, el stock se reserva temporalmente por **15 minutos**. Si el pago falla o expira, el stock se libera automáticamente (Job). Si el pago es exitoso, la reserva se consolida como descuento definitivo.
*   **RN-07 — Montos calculados en servidor**: El total del pedido siempre se calcula en el servidor (a partir de los precios en base de datos). El cliente no puede manipular precios desde el frontend.
*   **RN-08 — Idempotencia de webhooks**: Un evento de webhook con el mismo `event_id` solo se procesa una vez. Los reintentos de la pasarela siempre reciben un HTTP 200 sin efectos secundarios adicionales.

---

## 3. Ciclos de Vida de los Dominios

### 3.1 Ciclo de Vida del Pedido (`orders.status`)

Los estados del pedido reflejan el **estado operativo** del proceso de fulfillment, independientemente del estado del cobro.

| Estado | Código | Descripción | Transición siguiente |
| :--- | :--- | :--- | :--- |
| Borrador | `draft` | Pedido creado durante el proceso de checkout antes de confirmar pago. Uso interno, no visible al cliente. | `pending_payment` |
| Pendiente de Pago | `pending_payment` | Pedido registrado, esperando inicio o confirmación de pago. | `paid` / `validating` / `canceled` |
| Pagado | `paid` | Cobro confirmado automáticamente por la pasarela vía webhook. El stock se descuenta definitivamente. | `preparing` / `canceled` (por admin) |
| Validando | `validating` | El cliente subió el comprobante manual. Esperando revisión del Administrador. | `approved` / `pending_payment` (si se rechaza para re-subir) |
| Aprobado | `approved` | Administrador confirmó el pago manual. El stock se descuenta definitivamente. | `preparing` |
| En preparación | `preparing` | El administrador inició la preparación del pedido para despacho. | `shipped` |
| Despachado | `shipped` | El pedido fue entregado al courier. | `delivered` / `canceled` |
| Entregado | `delivered` | El cliente recibió el pedido (estado final exitoso). | — |
| Cancelado | `canceled` | Pedido cancelado (pago fallido, expiración, rechazo, o por admin). El stock se libera si estaba reservado. | — |

> **Nota de implementación**: Para el MVP V1.0, se implementan los estados: `pending_payment`, `paid`, `validating`, `approved`, `preparing`, y `canceled`. Los estados `shipped` y `delivered` se documentan para consistencia de diseño pero su gestión queda fuera del alcance del MVP.

### 3.2 Ciclo de Vida del Pago (`payments.status`)

Los estados del pago reflejan el **estado del cobro** asociado, independientemente del estado operativo del pedido.

| Estado | Código | Descripción |
| :--- | :--- | :--- |
| Pendiente | `pending` | Intento de pago registrado. La pasarela está procesando o el cliente aún no ha subido el comprobante. |
| Procesando | `processing` | La pasarela está procesando activamente el cobro (ej. 3D Secure en curso). |
| Completado | `completed` | El cobro fue procesado exitosamente y los fondos están confirmados. |
| Fallido | `failed` | El cobro fue rechazado (fondos insuficientes, tarjeta expirada, error de la pasarela). |
| Expirado | `expired` | La ventana de pago (15 min) o la sesión de la pasarela venció sin que el cliente completara el proceso. |
| Reembolsado | `refunded` | El cobro fue revertido total o parcialmente. (Alcance V1.0: documentado pero no automatizado.) |

### 3.3 Regla de Correlación entre Dominios

| Evento | Estado del Pago resultante | Estado del Pedido resultante |
| :--- | :--- | :--- |
| Cliente elige Tarjeta y confirma checkout | `pending` | `pending_payment` |
| Pasarela confirma cobro exitoso (webhook) | `completed` | `paid` |
| Pasarela notifica fallo o rechazo | `failed` | `canceled` |
| Tiempo de reserva de stock expira (15 min) | `expired` | `canceled` |
| Cliente sube comprobante manual | `pending` (sin cambio) | `validating` |
| Admin aprueba comprobante manual | `completed` | `approved` |
| Admin rechaza comprobante manual | `failed` | `pending_payment` |

---

## 4. Referencias y Dependencias
*   [01-business/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/01-business/README.md)
*   [11-decisions/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/11-decisions/README.md)
*   [02-requirements/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/02-requirements/README.md)
*   [05-database/02-schema-definition.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/05-database/02-schema-definition.md)
