# Sección 05: Modelo y Estructura de Base de Datos - Almacenes al Costo

## 1. Propósito de la Sección
Esta sección define la estructura de almacenamiento de información de la plataforma. Describe el esquema relacional de la base de datos MySQL, los tipos de datos, las restricciones de integridad y la estrategia de inicialización mediante migraciones y semillas (seeders).

---

## 2. Objetivos
*   Definir el Modelo Entidad-Relación conceptual y lógico del sistema.
*   Especificar detalladamente las tablas, columnas, tipos de datos, índices y llaves foráneas.
*   Establecer la estrategia para el poblamiento inicial de datos requeridos para el catálogo y la administración básica.

---

## 3. Estructura Interna Recomendada de Documentos

Esta carpeta contendrá la documentación física y lógica de los datos:

### [01-entity-relationship.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/05-database/01-entity-relationship.md)
*   **Propósito**: Modelar gráficamente y explicar las relaciones entre entidades.
*   **Contenido esperado**:
    *   Diagrama Entidad-Relación utilizando notación de Crow's Foot.
    *   Explicación de las cardinalidades críticas (ej. un Pedido pertenece a un Cliente/Usuario; un Pedido tiene muchos Detalles de Pedido; un Producto pertenece a una Categoría).

### [02-schema-definition.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/05-database/02-schema-definition.md)
*   **Propósito**: Detallar el esquema físico de las tablas MySQL.
*   **Contenido esperado**:
    *   Estructura SQL o pseudo-código de creación de tablas:
        *   `users` (administradores y clientes registrados post-compra).
        *   `categories` (categorías y subcategorías).
        *   `products` (información técnica, imágenes, precios y pertenencia a categorías).
        *   `inventories` (stock disponible, stock mínimo).
        *   `orders` (pedidos del checkout, datos de envío del cliente, estado del pago, etc.).
        *   `order_items` (detalle de productos, cantidades y precios en el momento de la compra).
        *   `payment_receipts` (registro de los comprobantes subidos por los clientes, ruta del archivo, fecha de subida, estado de validación).
        *   `promotions` (cupones de descuento, fechas de validez y montos/porcentajes).
        *   `settings` (configuraciones globales del sistema, como datos bancarios y QR de Deuna).

### [03-data-dictionary.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/05-database/03-data-dictionary.md)
*   **Propósito**: Definir cada campo, su tipo de dato exacto, obligatoriedad y comentarios de negocio.
*   **Contenido esperado**:
    *   Tablas de diccionario de datos con columnas: Campo, Tipo, Nulo, Llave, Por Defecto, Descripción.

### [04-seeders-migrations.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/05-database/04-seeders-migrations.md)
*   **Propósito**: Especificar los scripts de migración y semilla necesarios para el despliegue del MVP.
*   **Contenido esperado**:
    *   Orden de ejecución de migraciones (evitando errores de llaves foráneas).
    *   Datos de semilla requeridos: Un administrador por defecto, categorías iniciales, datos de prueba para productos, y configuración por defecto para datos bancarios de transferencia/Deuna.

---

## 4. Dependencias con otros Documentos
*   **01-business/01-business-rules.md**: Las restricciones de integridad y campos de la base de datos deben hacer cumplir las reglas operacionales.
*   **03-architecture/01-system-architecture.md**: Define cómo se conectará Laravel a MySQL mediante Eloquent ORM.
*   **06-backend/03-eloquent-models.md**: Los modelos de Laravel y sus relaciones deben coincidir de forma idéntica con el esquema de tablas especificado aquí.

---

## 5. Observaciones de Inconsistencias
*   *Clientes sin Registro en la Tabla de Usuarios*: Debido a que la compra no requiere cuenta, es necesario definir si los datos de los clientes que compran sin cuenta se almacenarán en una tabla de `customers` independiente, en la tabla de `orders` de forma redundante, o si se creará un registro de "usuario temporal" en `users` sin contraseña asignada. (Se recomienda usar una tabla de `customers` o almacenar los datos directamente en `orders` para mantener limpio el flujo de autenticación de `users`, vinculando la orden a un usuario real solo si decide crear su cuenta al final).
