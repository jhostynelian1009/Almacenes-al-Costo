# Documento: Diccionario de Datos

> **Alineación:** Cliente puede existir sin User; cuenta de cliente es opcional. Comprobante es el archivo privado de pago manual y Pago en revisión el estado posterior a su carga. Gateway/webhook es vocabulario futuro.

## 1. Objetivos del Documento
Describir detalladamente el propósito, tipo y restricciones de cada columna de las tablas de la base de datos para proveer una referencia inequívoca a los desarrolladores.

## 2. Diccionario de Datos de Tablas Clave

### Tabla: `categories`
| Campo | Tipo | Nulo | Llave | Predeterminado | Descripción |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | BIGINT UNSIGNED | NO | PK | | Identificador único autoincremental de la categoría |
| `name` | VARCHAR(255) | NO | UNI | | Nombre único de la categoría |
| `slug` | VARCHAR(255) | NO | UNI | | Identificador único legible para URLs |
| `description` | TEXT | SI | | NULL | Descripción opcional de la categoría |
| `image` | VARCHAR(255) | SI | | NULL | Ruta opcional de la imagen asociada |
| `icon` | VARCHAR(255) | SI | | NULL | Referencia opcional del icono asociado |
| `parent_id` | BIGINT UNSIGNED | SI | FK | NULL | Categoría padre; `NULL` identifica una categoría principal |
| `display_order` | INT UNSIGNED | NO | | 0 | Orden de presentación ascendente |
| `is_active` | BOOLEAN | NO | | true | Determina si la categoría puede mostrarse en el sitio público |
| `created_at` | TIMESTAMP | SI | | NULL | Fecha de creación |
| `updated_at` | TIMESTAMP | SI | | NULL | Fecha de última actualización |

`parent_id` referencia `categories.id` con eliminación restringida y permite jerarquías de múltiples niveles. No puede coincidir con el propio `id`; la aplicación debe impedir ciclos. Una categoría no puede eliminarse mientras tenga subcategorías o productos asociados. Las consultas públicas incluyen únicamente categorías activas y se ordenan por `display_order` y después por `name`.

### Tabla: `orders`
| Campo | Tipo | Nulo | Llave | Predeterminado | Descripción |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | INT UNSIGNED | NO | PK | | Identificador único autoincremental de la orden |
| `user_id` | INT UNSIGNED | SI | FK | NULL | ID del usuario si decidió crear su cuenta, null si es invitado |
| `order_number` | VARCHAR(50) | NO | UNI | | Código legible e inequívoco del pedido para rastreo |
| `customer_name` | VARCHAR(255) | NO | | | Nombre completo ingresado en el checkout |
| `customer_document`| VARCHAR(20) | NO | | | Cédula o RUC del cliente para facturación |
| `customer_phone` | VARCHAR(20) | NO | | | Teléfono de contacto (utilizado para envíos) |
| `customer_email` | VARCHAR(255) | NO | | | Correo electrónico para confirmaciones |
| `status` | ENUM | NO | | 'pending' | Estado del pedido: 'pending' (pendiente de pago), 'validating' (comprobante subido, validación manual), 'pagado' (confirmado por pasarela), 'approved' (aprobado manualmente), 'canceled' (rechazado/cancelado) |

### Tabla: `payments` (Registro General de Intentos de Cobro)
| Campo | Tipo | Nulo | Llave | Predeterminado | Descripción |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | INT UNSIGNED | NO | PK | | Identificador único autoincremental de la transacción de pago |
| `order_id` | INT UNSIGNED | NO | FK | | Asociación directa al pedido correspondiente |
| `gateway` | VARCHAR(50) | NO | | | Proveedor seleccionado: 'stripe', 'payphone', 'datafast', 'kushki', 'manual' |
| `payment_method` | VARCHAR(30) | NO | | | Método físico: 'card' (tarjeta), 'transfer' (transferencia), 'deuna' |
| `amount` | DECIMAL(10,2) | NO | | | Importe total cargado al cliente |
| `status` | ENUM | NO | | 'pending' | Estado del cobro: 'pending' (iniciado), 'completed' (pagado), 'failed', 'refunded' |
| `transaction_id` | VARCHAR(150) | SI | UNI | NULL | ID de confirmación externo devuelto por la pasarela de pagos |
| `created_at` | TIMESTAMP | SI | | | Fecha de creación del intento de pago |
| `updated_at` | TIMESTAMP | SI | | | Fecha de actualización del estado de pago |

### Tabla: `payment_transactions` (Historial y Auditoría de Respuestas)
| Campo | Tipo | Nulo | Llave | Predeterminado | Descripción |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | INT UNSIGNED | NO | PK | | ID autoincremental de log de transacción |
| `payment_id` | INT UNSIGNED | NO | FK | | Vínculo al registro general de pago |
| `event_type` | ENUM | NO | | | Tipo de evento: 'request' (envío), 'response' (recepción), 'webhook_received', 'error' |
| `payload` | JSON | NO | | | Respuesta o petición HTTP cruda y completa del proveedor para depuración |
| `created_at` | TIMESTAMP | SI | | | Estampa de tiempo exacta del registro de log |

### Tabla: `webhook_logs` (Idempotencia y Trazabilidad)
| Campo | Tipo | Nulo | Llave | Predeterminado | Descripción |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `id` | INT UNSIGNED | NO | PK | | ID autoincremental del log de webhook |
| `gateway` | VARCHAR(50) | NO | | | Pasarela emisora del webhook (ej. 'stripe') |
| `event_id` | VARCHAR(150) | NO | UNI | | Identificador único del evento enviado por el gateway para evitar cobros dobles |
| `payload` | JSON | NO | | | Contenido completo enviado en formato JSON |
| `processed` | BOOLEAN | NO | | false | Bandera que indica si el evento ya fue aplicado a la base de datos |
| `created_at` | TIMESTAMP | SI | | | Fecha de recepción de la petición |


## 3. Referencias y Dependencias
*   [05-database/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/05-database/README.md)
*   [05-database/02-schema-definition.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/05-database/02-schema-definition.md)
