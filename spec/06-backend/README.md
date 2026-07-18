# Sección 06: Lógica de Servidor (Backend) - Almacenes al Costo

## 1. Propósito de la Sección
Esta sección detalla el diseño de la lógica del lado del servidor de la aplicación construida en Laravel/PHP. Especifica la definición de las rutas HTTP, las responsabilidades de los controladores, el comportamiento de los modelos de Eloquent y la lógica para operaciones auxiliares como la subida segura de comprobantes.

---

## 2. Objetivos
*   Definir un mapa claro de rutas de Laravel divididas por middlewares de seguridad (públicas vs. protegidas por autenticación).
*   Especificar los controladores encargados de procesar la lógica de negocio del carrito, el checkout y la validación manual de pedidos por el administrador.
*   Documentar las clases del modelo de datos de Eloquent y sus respectivas relaciones de Laravel.

---

## 3. Estructura Interna Recomendada de Documentos

Esta carpeta contendrá los siguientes documentos de especificación técnica del backend:

### [01-routes-map.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/06-backend/01-routes-map.md)
*   **Propósito**: Definir y organizar las rutas del archivo `routes/web.php` y `routes/api.php`.
*   **Contenido esperado**:
    *   *Rutas Públicas del Cliente*: Home (`/`), Catálogo (`/catalog`), Detalle (`/product/{id}`), Carrito (`/cart`), Checkout (`/checkout`), Confirmación (`/order/confirm`), Métodos de Pago (`/order/{id}/payment`), Subida de Comprobante (`/order/{id}/payment/upload`), Estado del Pedido (`/order/{id}/status`).
    *   *Rutas Privadas del Administrador* (agrupadas bajo prefijo `/admin` y middleware `auth`): Dashboard, CRUDs de Productos, Categorías, Inventarios, Pedidos (con aprobación/rechazo), Clientes, Promociones y Configuración.

### [02-controllers.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/06-backend/02-controllers.md)
*   **Propósito**: Detallar las clases controladoras de Laravel y las acciones (métodos) de cada una.
*   **Contenido esperado**:
    *   `ProductController`: Métodos `index` y `show` para el catálogo público.
    *   `CartController`: Manejo de lógica en sesiones (si aplica) o validación de items del carrito local.
    *   `CheckoutController`: Procesamiento del formulario de compra y guardado de datos.
    *   `PaymentController`: Presentación de cuentas y manejo de la carga de archivos de comprobante.
    *   `Admin\OrderController`: Listado de pedidos y lógica de cambio de estado a aprobado/rechazado.
    *   `Admin\ProductController`: CRUD de productos, incluyendo la gestión de archivos multimedia subidos.

### [03-eloquent-models.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/06-backend/03-eloquent-models.md)
*   **Propósito**: Detallar los modelos Eloquent de Laravel.
*   **Contenido esperado**:
    *   Declaración de las propiedades, `$fillable` y métodos de relación (`belongsTo`, `hasMany`, `belongsToMany`).
    *   Uso de Scopes para filtrado de catálogo (ej. por categoría, rango de precios o texto de búsqueda) y estados de pedidos.

### [04-services-helpers.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/06-backend/04-services-helpers.md)
*   **Propósito**: Describir clases de servicios auxiliares o helpers lógicos independientes.
*   **Contenido esperado**:
    *   `ReceiptUploadService`: Encargado de la subida, almacenamiento en disco y generación de rutas seguras para los comprobantes.
    *   `OrderNotificationService`: Lógica para preparar notificaciones o enlaces directos de correo o WhatsApp en el futuro.

---

## 4. Dependencias con otros Documentos
*   **02-requirements/01-functional-requirements.md**: Especifica qué acciones debe realizar el backend para cumplir con los RF.
*   **05-database/02-schema-definition.md**: Provee las tablas y relaciones sobre las cuales actúan los modelos de Eloquent.
*   **07-frontend/README.md**: Se comunica con los controladores mediante peticiones HTTP (GET, POST) y llamadas de Blade.
*   **08-security/01-auth-authorization.md**: Dicta los middlewares y guardias que se deben aplicar a las rutas del backend.

---

## 5. Observaciones de Inconsistencias
*   *Gestión del Carrito de Compras*: Debe aclararse en los requerimientos si el carrito se almacenará en la base de datos (requiriendo sesiones persistentes en base de datos) o enteramente en el frontend (ej. `localStorage` o `sessionStorage`) y enviado al backend en formato JSON durante el Checkout. (Se recomienda realizar la persistencia del carrito en el frontend del cliente para evitar consumo de base de datos de usuarios no registrados, enviando la estructura al backend en el momento de crear el pedido).
