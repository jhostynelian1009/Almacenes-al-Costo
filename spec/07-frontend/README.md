# Sección 07: Interfaz de Usuario (Frontend) - Almacenes al Costo

## 1. Propósito de la Sección
Esta sección detalla la implementación visual e interactiva de la aplicación del lado del cliente. Describe el uso de plantillas Blade de Laravel, la integración con Bootstrap 5 para el diseño responsive y el uso de JavaScript nativo para la manipulación dinámica del carrito y la interactividad de la interfaz.

---

## 2. Objetivos
*   Definir la estructura de vistas de Laravel Blade organizadas por layouts para Cliente y Administrador.
*   Especificar los módulos JavaScript necesarios para la gestión del carrito local y la carga interactiva de archivos.
*   Establecer la hoja de estilos personalizada de CSS que complementará y elevará visualmente a Bootstrap 5.

---

## 3. Estructura Interna Recomendada de Documentos

Esta carpeta contendrá las especificaciones técnicas del frontend de la aplicación:

### [01-blade-views.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/07-frontend/01-blade-views.md)
*   **Propósito**: Definir la jerarquía y organización de los archivos de vistas de Blade (`resources/views`).
*   **Contenido esperado**:
    *   *Layouts*:
        *   `layouts/app.blade.php`: Plantilla principal para el cliente (con Navbar, Footer, links a WhatsApp y scripts de carrito).
        *   `layouts/admin.blade.php`: Plantilla para el dashboard del administrador (con barra lateral de navegación y área de notificaciones).
    *   *Vistas del Cliente*: Carpetas y archivos Blade organizados para Inicio, Catálogo, Detalle de Producto, Carrito, Checkout, Métodos de Pago, Subida de Comprobante, etc.
    *   *Vistas del Administrador*: Carpetas y archivos para Login, Dashboard, CRUDs (Productos, Categorías, Inventarios, Pedidos, Clientes, Promociones, Configuración).

### [02-javascript-modules.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/07-frontend/02-javascript-modules.md)
*   **Propósito**: Detallar el comportamiento interactivo de la interfaz web mediante JavaScript.
*   **Contenido esperado**:
    *   `cart.js`: Módulo encargado de gestionar el carrito en `localStorage` (agregar productos, eliminar, actualizar cantidades, calcular subtotales y actualizar el contador de la Navbar en tiempo real).
    *   `checkout.js`: Lógica para preparar la petición POST de creación del pedido inyectando los datos del formulario de checkout y los items del carrito.
    *   `receipt-upload.js`: Gestión visual del formulario de carga de comprobantes (validación del formato antes de subir, previsualización de la imagen cargada y barra de progreso de subida).

### [03-bootstrap-custom.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/07-frontend/03-bootstrap-custom.md)
*   **Propósito**: Documentar las personalizaciones visuales sobre el framework Bootstrap 5.
*   **Contenido esperado**:
    *   Definición de clases personalizadas (ej. `.card-product`, `.btn-checkout`, `.bg-gradient-premium`).
    *   Integración de íconos (Bootstrap Icons o FontAwesome) para mejorar la usabilidad visual de la navegación y las opciones del administrador.

---

## 4. Dependencias con otros Documentos
*   **04-ui-ux/02-design-system.md**: Traduce las decisiones visuales, la tipografía y los colores de la guía de estilos en código CSS y clases Bootstrap utilizables.
*   **06-backend/01-routes-map.md**: Las vistas Blade deben usar las funciones `route()` de Laravel para generar enlaces consistentes que apunten a los endpoints del servidor.

---

## 5. Observaciones de Inconsistencias
*   *Uso de Bootstrap*: El proyecto utiliza Bootstrap 5. Se debe evitar la inclusión de frameworks reactivos pesados (como Vue o React) para mantener el proyecto simple, ligero y fácil de mantener en Blade y JavaScript nativo. Las operaciones del carrito deben resolverse sin requerir frameworks SPA complejas.
*   *Carga de archivos*: Se sugiere asegurar que la vista de subida de comprobante admita formatos amigables para el usuario móvil (como capturas de pantalla tomadas directamente desde la app de banca móvil o Deuna), lo cual requiere optimizar el input de archivo para abrir la cámara en dispositivos móviles.
