# Sección 04: Diseño de Interfaz y Experiencia de Usuario (UI/UX) - Almacenes al Costo

## 1. Propósito de la Sección
Esta sección define las especificaciones visuales, de interacción y de navegación del sistema. Su objetivo es garantizar una interfaz atractiva, intuitiva y fluida, optimizada tanto para clientes móviles (compradores rápidos) como para administradores (gestión eficiente).

---

## 2. Objetivos
*   Definir el mapa de navegación completo y las transiciones de pantalla para clientes y administradores.
*   Establecer la guía de estilos (Bootstrap 5, colores, tipografía, responsive) y el diseño premium de la aplicación.
*   Especificar los campos, interacciones y validaciones visuales requeridos en cada una de las pantallas.

---

## 3. Estructura Interna Recomendada de Documentos

Esta carpeta contendrá los siguientes documentos sobre la interfaz del sistema:

### [01-navigation-map.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/04-ui-ux/01-navigation-map.md)
*   **Propósito**: Mostrar el mapa de sitio y los caminos de navegación de los usuarios.
*   **Contenido esperado**:
    *   Flujo de navegación del Cliente: Catálogo -> Carrito -> Checkout -> Confirmación del Pedido -> Subir Comprobante -> Confirmación.
    *   Flujo de navegación del Administrador: Login -> Dashboard -> Módulos (Productos, Categorías, Inventario, Pedidos, etc.).

### [02-design-system.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/04-ui-ux/02-design-system.md)
*   **Propósito**: Consolidar la identidad visual del proyecto.
*   **Contenido esperado**:
    *   Paleta de colores premium (evitando colores genéricos y enfocándose en tonalidades modernas y elegantes).
    *   Tipografía (fuentes sugeridas de Google Fonts como Inter u Outfit).
    *   Componentes Bootstrap personalizados (diseño de cards de producto, tablas del admin, botones de acción).
    *   Directrices de responsive design (Mobile First para el flujo del cliente; Desktop First para el panel del administrador).

### [03-screen-specs.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/04-ui-ux/03-screen-specs.md)
*   **Propósito**: Detallar el contenido y comportamiento de cada pantalla de la aplicación.
*   **Contenido esperado**:
    *   *Pantallas del Cliente (1 al 10)*: Lista detallada de componentes visuales, modales y formularios (ej. campos del Checkout, diseño del visor del método de pago Deuna).
    *   *Pantallas del Administrador (0 al 12)*: Diseño de la grilla de productos, visualizador de imágenes de comprobantes y formulario de productos con dropzone.

---

## 4. Dependencias con otros Documentos
*   **00-project/README.md**: Lista los módulos funcionales del cliente y administrador que deben ser provistos de una pantalla.
*   **02-requirements/01-functional-requirements.md**: Las pantallas deben responder a los requerimientos funcionales del sistema (ej. mostrar stock actual, botones para aplicar cupones de promoción).
*   **07-frontend/01-blade-views.md**: Sirve de base directa para que el programador de vistas Blade maquete la interfaz basándose en las especificaciones UI/UX detalladas aquí.

---

## 5. Observaciones de Inconsistencias
*   *Flujo Opcional de Crear Cuenta*: El paso 10 del flujo del cliente ("Crear cuenta opcional") requiere especial cuidado a nivel UX para no romper el proceso de compra. Debe diseñarse como un botón destacado en la pantalla final de "Confirmación", permitiendo al cliente ingresar una contraseña y guardar sus datos de envío ya capturados en el checkout para futuras compras.
