# Documento: Especificación de Seeders y Migraciones

> **Vigencia:** Migraciones de webhook/transacciones automáticas quedan diferidas. El MVP prioriza usuarios/clientes, categorías, productos, inventario, órdenes/items, métodos manuales, pagos, comprobantes e historial. No se siembran datos comerciales inventados.

## 1. Objetivos del Documento
Definir los requerimientos de scripts para la creación automática de tablas y la inyección inicial de datos clave del sistema.

## 2. Plan de Migraciones
1.  `create_users_table`: Estructura estándar de Laravel con campos adicionales si aplican.
2.  `create_categories_table`: Incluye `id`; `name` y `slug` únicos; `description`, `image` e `icon` opcionales; autorrelación nullable mediante `parent_id` con eliminación restringida; `display_order` unsigned con valor predeterminado `0`; `is_active` con valor predeterminado `true`; y timestamps. La migración debe impedir la autorreferencia directa.
3.  `create_products_table`: Incluye llave foránea a `categories` con eliminación restringida.
4.  `create_inventories_table`: Incluye llave foránea a `products`.
5.  `create_orders_table`: Tabla de pedidos.
6.  `create_order_items_table`: Detalles de pedidos.
7.  `create_payments_table`: Registro centralizado de intentos de pago vinculados a pedidos.
8.  `create_payment_transactions_table`: Log y auditoría de payloads de pasarelas vinculado a pagos.
9.  `create_payment_receipts_table`: Registro de archivos de comprobante (con FK a `orders` y `payments` nullable).
10. `create_webhook_logs_table`: Bitácora para idempotencia de llamadas webhook de pasarelas.
11. `create_promotions_table`: Cupones y ofertas.
12. `create_settings_table`: Configuraciones de cuentas bancarias, QR de Deuna y llaves/credenciales de pasarelas de pago.

## 3. Especificación de Seeders
*   **UserSeeder**: Crear un usuario administrador por defecto (`admin@almacenesalcosto.com`) con una contraseña segura para el primer inicio de sesión.
*   **CategorySeeder**: Poblar las categorías iniciales (ej. *Electrodomésticos*, *Muebles*, *Tecnología*, *Decoración*).
*   **ProductSeeder**: Poblar productos de demostración detallados para facilitar el testeo de grillas y filtros del catálogo.
*   **SettingSeeder**: Registrar los datos bancarios del negocio por defecto para transferencia, la ruta inicial para el QR de Deuna, y llaves/tokens vacíos/entorno para las pasarelas de pago (Stripe, PayPhone, Datafast, Kushki).

## 4. Referencias y Dependencias
*   [05-database/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/05-database/README.md)
*   [10-deployment/02-deployment-guide.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/10-deployment/02-deployment-guide.md)
