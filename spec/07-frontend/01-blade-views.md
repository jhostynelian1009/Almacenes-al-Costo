# Documento: Plantillas y Vistas Blade

## 1. Objetivos del Documento
Especificar la jerarquía de las vistas en `resources/views` y definir el contenido y modularidad de las plantillas Blade.

## 2. Organización de Vistas

### Layout Principal: `resources/views/layouts/app.blade.php`
*   **Encabezado**: Meta tags de responsive y SEO, cargador de Google Fonts (font Outfit/Inter) y estilos de Bootstrap 5.
*   **Navbar**: Logo de "Almacenes al Costo", enlace al catálogo, indicador visual dinámico del Carrito (con burbuja de número de items).
*   **Sección Principal**: Directiva `@yield('content')`.
*   **Pie de página (Footer)**: Enlaces institucionales, métodos de contacto (con el enlace directo a WhatsApp) y avisos legales.

### Vistas del Cliente
*   `client/home.blade.php`: Banner principal y grilla con productos destacados de la base de datos.
*   `client/catalog.blade.php`: Buscador lateral y grilla responsiva de productos.
*   `client/cart.blade.php`: Tabla de productos en el carrito con botones de incrementar/decrementar cantidad y botón para avanzar al checkout.
*   `client/checkout.blade.php`: Formulario del checkout inteligente, selección de método de pago (Tarjeta, Transferencia, Deuna) y resumen de totales.
*   `client/payment.blade.php`: Pantalla puente. Si es manual, muestra datos de transferencia o QR y cargador de comprobante. Si es tarjeta, gestiona la redirección o modal tokenizado.
*   `client/payment/success.blade.php`: Vista de confirmación inmediata al confirmar cobro por pasarela (pedido en estado "PAGADO") con opción para crear cuenta.
*   `client/payment/failed.blade.php`: Mensaje de error/rechazo en pasarela con botón para volver al checkout manteniendo el carrito.
*   `client/payment/pending.blade.php`: Mensaje indicando que el pago está en verificación asíncrona de pasarela, instrucciones de no duplicar e información de contacto.
*   `client/confirmation.blade.php`: Mensaje de agradecimiento para pedidos manuales (estado "Validando") con opción de Crear Cuenta.

## 3. Referencias y Dependencias
*   [07-frontend/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/07-frontend/README.md)
*   [04-ui-ux/03-screen-specs.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/04-ui-ux/03-screen-specs.md)
