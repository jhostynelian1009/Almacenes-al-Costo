# Documento: Definición del Esquema Relacional

> **Alineación MVP:** `customers.user_id` es nullable. El esquema inicial soporta métodos manuales, comprobantes, estado, `reviewed_by`, `reviewed_at`, observación/`rejection_reason` e historial. Tablas exclusivas de gateway, tarjeta o webhook son futuras.

## 1. Objetivos del Documento
Especificar la definición física de las tablas de la base de datos MySQL, sus atributos, restricciones relacionales e índices recomendados para el rendimiento óptimo en producción.

> **Fuente de verdad de estados**: Los valores de los ENUMs de esta sección son la definición canónica. Todos los documentos del SPEC deben referenciar estos valores exactos.

---

## 2. Definición de Tablas (DDL Conceptual)

### Tabla: `users`
*   `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
*   `name` VARCHAR(255) NOT NULL
*   `email` VARCHAR(255) UNIQUE NOT NULL — **ÍNDICE ÚNICO**
*   `password` VARCHAR(255) NOT NULL (Hash bcrypt)
*   `email_verified_at` TIMESTAMP NULL
*   `remember_token` VARCHAR(100) NULL
*   `created_at` TIMESTAMP
*   `updated_at` TIMESTAMP

### Tabla: `categories`
*   `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
*   `name` VARCHAR(255) UNIQUE NOT NULL — **ÍNDICE ÚNICO**
*   `slug` VARCHAR(255) UNIQUE NOT NULL — **ÍNDICE ÚNICO**
*   `description` TEXT NULL
*   `image` VARCHAR(255) NULL
*   `icon` VARCHAR(255) NULL
*   `parent_id` BIGINT UNSIGNED NULL (`foreignId` nullable) FOREIGN KEY REFERENCES `categories(id)` ON DELETE RESTRICT
*   `display_order` INT UNSIGNED NOT NULL DEFAULT 0
*   `is_active` BOOLEAN NOT NULL DEFAULT TRUE
*   `created_at` TIMESTAMP
*   `updated_at` TIMESTAMP
*   **ÍNDICE**: `INDEX idx_categories_parent_id (parent_id)`
*   **RESTRICCIÓN**: `CHECK (parent_id IS NULL OR parent_id <> id)`

Una categoría con `parent_id = NULL` es principal. La autorrelación permite múltiples niveles mediante una categoría padre y múltiples subcategorías. La aplicación debe impedir ciclos jerárquicos, excluir categorías inactivas del sitio público y ordenar las categorías públicas por `display_order` ascendente y luego por `name` ascendente. No se permite eliminar una categoría con subcategorías o productos asociados; las llaves foráneas usan `ON DELETE RESTRICT` como respaldo de integridad.

### Tabla: `products`
*   `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
*   `category_id` BIGINT UNSIGNED NOT NULL FOREIGN KEY REFERENCES `categories(id)` ON DELETE RESTRICT
*   `name` VARCHAR(255) NOT NULL
*   `slug` VARCHAR(255) UNIQUE NOT NULL — **ÍNDICE ÚNICO**
*   `sku` VARCHAR(50) UNIQUE NOT NULL — **ÍNDICE ÚNICO**
*   `description` TEXT NULL
*   `price` DECIMAL(10,2) NOT NULL
*   `image` VARCHAR(255) NULL
*   `is_active` BOOLEAN DEFAULT TRUE
*   `created_at` TIMESTAMP
*   `updated_at` TIMESTAMP
*   **ÍNDICE**: `INDEX idx_products_category_id (category_id)`
*   **ÍNDICE**: `INDEX idx_products_is_active (is_active)`

#### Contrato del identificador público de producto

`products.slug` se utiliza exclusivamente como identificador público persistente y clave de resolución de rutas de Product. No sustituye la clave primaria `id`, no sustituye `sku` y no contiene información sensible. Ni `products.id` ni `products.sku` se exponen en las URLs públicas.

El slug se genera en el servidor al crear el producto a partir de `name`: se eliminan espacios iniciales y finales, se normaliza a minúsculas, se utilizan caracteres compatibles con URLs y las palabras se separan mediante guiones. No puede contener barras, query strings ni fragmentos. Por ejemplo, `Aceite Castrol GTX 20W-50` genera `aceite-castrol-gtx-20w-50`. El alta administrativa no acepta un slug enviado libremente por el formulario.

La unicidad se resuelve conservando el slug base para el primer producto y agregando un sufijo numérico incremental cuando exista una colisión: `aceite-castrol`, `aceite-castrol-2`, `aceite-castrol-3`. La comprobación incluye productos activos, inactivos y eliminados lógicamente; los slugs de productos soft-deleted no se reutilizan automáticamente. La restricción `UNIQUE` de la base de datos constituye la protección final.

El slug es estable: se genera al crear el producto y un cambio posterior de `name` no lo modifica automáticamente. FT-003.4 no incorpora una interfaz administrativa para editarlo. Cualquier edición manual futura, redirección o historial de slugs requerirá un contrato específico posterior.

La implementación deberá incorporar una migración de transición para los productos existentes. Esta añadirá primero `slug` de forma compatible con registros previos, procesará todos los productos —incluidos los soft-deleted— en un orden determinista, generará los valores y resolverá colisiones mediante sufijos incrementales. Solo después aplicará la obligatoriedad y la unicidad contractuales. La transición no eliminará ni modificará `id`, `sku`, `name`, inventario, imágenes, relaciones ni marcas de eliminación lógica. Esta migración no forma parte de la rama documental que define el contrato.

### Tabla: `inventories`
*   `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
*   `product_id` INT UNSIGNED NOT NULL UNIQUE FOREIGN KEY REFERENCES `products(id)` ON DELETE CASCADE — relación 1:1 con products
*   `stock` INT UNSIGNED NOT NULL DEFAULT 0 (Stock físico disponible)
*   `reserved_stock` INT UNSIGNED NOT NULL DEFAULT 0 (Stock bloqueado temporalmente por transacciones en curso)
*   `min_stock` INT UNSIGNED NOT NULL DEFAULT 5 (Umbral de alerta para el administrador)
*   `updated_at` TIMESTAMP

> **Nota**: `stock` = existencias físicas reales; `reserved_stock` = cantidad bloqueada para pedidos con pago en curso. El stock "disponible para venta" se calcula como `stock - reserved_stock`.

### Tabla: `promotions`
*   `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
*   `code` VARCHAR(50) UNIQUE NOT NULL — **ÍNDICE ÚNICO**
*   `discount_type` ENUM('percentage', 'fixed') NOT NULL
*   `discount_value` DECIMAL(10,2) NOT NULL
*   `valid_from` DATE NULL
*   `valid_until` DATE NULL
*   `max_uses` INT UNSIGNED NULL
*   `used_count` INT UNSIGNED DEFAULT 0
*   `is_active` BOOLEAN DEFAULT TRUE
*   `created_at` TIMESTAMP
*   `updated_at` TIMESTAMP

### Tabla: `orders`
*   `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
*   `user_id` BIGINT UNSIGNED NULL FOREIGN KEY REFERENCES `users(id)` ON DELETE SET NULL (Vínculo opcional, se asigna si el cliente crea cuenta post-compra)
*   `order_number` VARCHAR(50) UNIQUE NOT NULL — **ÍNDICE ÚNICO** (formato: PED-AAAAMMDD-NNN)
*   `promotion_id` INT UNSIGNED NULL FOREIGN KEY REFERENCES `promotions(id)` ON DELETE SET NULL
*   `customer_name` VARCHAR(255) NOT NULL
*   `customer_document` VARCHAR(20) NOT NULL (Cédula o RUC)
*   `customer_phone` VARCHAR(20) NOT NULL
*   `customer_email` VARCHAR(255) NOT NULL
*   `shipping_address` TEXT NOT NULL
*   `subtotal` DECIMAL(10,2) NOT NULL
*   `tax` DECIMAL(10,2) NOT NULL DEFAULT 0.00
*   `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00
*   `total` DECIMAL(10,2) NOT NULL
*   `status` ENUM('pending_payment', 'paid', 'validating', 'approved', 'preparing', 'shipped', 'delivered', 'canceled') NOT NULL DEFAULT 'pending_payment'
*   `notes` TEXT NULL (Observaciones internas del administrador)
*   `created_at` TIMESTAMP
*   `updated_at` TIMESTAMP
*   **ÍNDICE**: `INDEX idx_orders_status (status)`
*   **ÍNDICE**: `INDEX idx_orders_customer_email (customer_email)`
*   **ÍNDICE**: `INDEX idx_orders_user_id (user_id)`

### Tabla: `order_items`
*   `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
*   `order_id` BIGINT UNSIGNED NOT NULL FOREIGN KEY REFERENCES `orders(id)` ON DELETE CASCADE
*   `product_id` INT UNSIGNED NOT NULL FOREIGN KEY REFERENCES `products(id)` ON DELETE RESTRICT
*   `product_name` VARCHAR(255) NOT NULL (Snapshot del nombre al momento de la compra)
*   `product_sku` VARCHAR(50) NOT NULL (Snapshot del SKU al momento de la compra)
*   `quantity` INT UNSIGNED NOT NULL
*   `unit_price` DECIMAL(10,2) NOT NULL (Snapshot del precio al momento de la compra)
*   `subtotal` DECIMAL(10,2) NOT NULL (quantity × unit_price)
*   **ÍNDICE**: `INDEX idx_order_items_order_id (order_id)`

### Tabla: `payments`
*   `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
*   `order_id` BIGINT UNSIGNED NOT NULL FOREIGN KEY REFERENCES `orders(id)` ON DELETE RESTRICT
*   `gateway` VARCHAR(50) NOT NULL — valores permitidos: `'stripe'`, `'payphone'`, `'datafast'`, `'kushki'`, `'manual'`
*   `payment_method` VARCHAR(30) NOT NULL — valores permitidos: `'card'`, `'transfer'`, `'deuna'`
*   `amount` DECIMAL(10,2) NOT NULL
*   `currency` VARCHAR(3) NOT NULL DEFAULT 'USD'
*   `status` ENUM('pending', 'processing', 'completed', 'failed', 'expired', 'refunded') NOT NULL DEFAULT 'pending'
*   `transaction_id` VARCHAR(255) NULL (ID externo provisto por la pasarela, único por pasarela)
*   `gateway_response_code` VARCHAR(50) NULL (Código de respuesta de la pasarela para debugging)
*   `created_at` TIMESTAMP
*   `updated_at` TIMESTAMP
*   **ÍNDICE**: `INDEX idx_payments_order_id (order_id)`
*   **ÍNDICE**: `INDEX idx_payments_status (status)`
*   **ÍNDICE**: `INDEX idx_payments_transaction_id (transaction_id)` (para búsquedas por ID externo)

> **Cardinalidad**: Un `order` puede tener múltiples `payments` (ej. el cliente reintenta el pago). Solo un `payment` por `order` tendrá `status = completed`.

### Tabla: `payment_transactions`
*   `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
*   `payment_id` BIGINT UNSIGNED NOT NULL FOREIGN KEY REFERENCES `payments(id)` ON DELETE CASCADE
*   `event_type` ENUM('request', 'response', 'webhook_received', 'callback_received', 'error') NOT NULL
*   `payload` JSON NOT NULL (Request/Response HTTP completo para auditoría y debugging)
*   `created_at` TIMESTAMP (Solo inserción, inmutable)
*   **ÍNDICE**: `INDEX idx_payment_transactions_payment_id (payment_id)`

### Tabla: `payment_receipts`
*   `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
*   `order_id` BIGINT UNSIGNED NOT NULL FOREIGN KEY REFERENCES `orders(id)` ON DELETE CASCADE — **ÍNDICE**
*   `payment_id` BIGINT UNSIGNED NULL FOREIGN KEY REFERENCES `payments(id)` ON DELETE SET NULL
*   `file_path` VARCHAR(500) NOT NULL (Ruta relativa en `storage/app/comprobantes/`)
*   `original_filename` VARCHAR(255) NULL (Nombre original del archivo para referencia del admin)
*   `payment_method` ENUM('transfer', 'deuna') NOT NULL
*   `transaction_reference` VARCHAR(100) NULL (Referencia o número de comprobante ingresado por el cliente)
*   `rejection_reason` TEXT NULL (Motivo de rechazo si el admin rechaza el comprobante)
*   `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP

### Tabla: `webhook_logs`
*   `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
*   `gateway` VARCHAR(50) NOT NULL
*   `event_type` VARCHAR(100) NULL (Tipo de evento según la pasarela, ej. `payment_intent.succeeded`)
*   `event_id` VARCHAR(255) UNIQUE NOT NULL — **ÍNDICE ÚNICO** (ID único del evento para garantizar idempotencia)
*   `payload` JSON NOT NULL (Contenido completo del webhook recibido)
*   `signature_verified` BOOLEAN NOT NULL DEFAULT FALSE (Resultado de la validación de firma HMAC)
*   `processed` BOOLEAN NOT NULL DEFAULT FALSE
*   `processing_error` TEXT NULL (Detalle del error si `processed = false` tras intento)
*   `created_at` TIMESTAMP
*   **ÍNDICE**: `INDEX idx_webhook_logs_gateway (gateway)`
*   **ÍNDICE**: `INDEX idx_webhook_logs_processed (processed)`

### Tabla: `settings`
*   `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
*   `key` VARCHAR(100) UNIQUE NOT NULL — **ÍNDICE ÚNICO**
*   `value` TEXT NULL
*   `group` VARCHAR(50) NULL (ej. `'bank'`, `'gateway'`, `'store'`)
*   `updated_at` TIMESTAMP

---

## 3. Referencias y Dependencias
*   [05-database/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/05-database/README.md)
*   [05-database/01-entity-relationship.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/05-database/01-entity-relationship.md)
*   [05-database/03-data-dictionary.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/05-database/03-data-dictionary.md)
*   [01-business/01-business-rules.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/01-business/01-business-rules.md)
