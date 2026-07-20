# Sección 00: Gestión del Proyecto - Almacenes al Costo

## 1. Propósito de la Sección
Esta sección establece la visión general, el alcance del Producto Mínimo Viable (MVP) y las tecnologías principales de la plataforma **Almacenes al Costo**. Sirve como el punto de inicio para cualquier desarrollador o agente de IA que requiera comprender el contexto general del negocio y los objetivos de la aplicación.

---

## 2. Objetivos del Proyecto

### Objetivo General
Desarrollar una plataforma web moderna, intuitiva y segura que optimice el proceso de venta y administración de Almacenes al Costo, facilitando la interacción entre clientes y administradores.

### Objetivos Específicos
*   Mostrar un catálogo organizado de productos de electrodomésticos, muebles y artículos de hogar.
*   Permitir a los clientes realizar pedidos desde la página web mediante un flujo de Checkout Inteligente simplificado.
*   Implementar una pasarela de pagos en línea desacoplada (Stripe, PayPhone, Datafast, Kushki, etc.) para el procesamiento automatizado de pagos con tarjeta de crédito/débito.
*   Mantener el sistema de validación manual de pagos a través de la carga de comprobantes (Transferencias bancarias y Deuna) como alternativas de pago adicionales.
*   Proporcionar al administrador un panel integral para gestionar productos, categorías, inventario, pedidos, clientes, promociones y configuraciones.
*   Garantizar una experiencia de usuario responsive y adaptada a dispositivos móviles.

---

## 3. Alcance del Proyecto (MVP)
La primera versión de la aplicación contempla los siguientes módulos y funcionalidades:

### Módulos del Cliente (Público)
1.  **Inicio**: Landing page con banners promocionales y productos destacados.
2.  **Catálogo**: Vista general de productos con buscadores y filtros por categoría/precio.
3.  **Detalle del Producto**: Ficha técnica, precio, stock e imágenes del producto.
4.  **Carrito de Compras**: Gestión de productos seleccionados de forma local.
5.  **Checkout Inteligente**: Formulario simplificado para la recolección de datos del cliente y selección de método de pago.
6.  **Confirmación del Pedido**: Generación del número de pedido y registro de la orden en la base de datos.
7.  **Método de Pago**: Selección de método de pago: Tarjeta (Redirección o modal seguro de la pasarela), Transferencia Bancaria directa, o Deuna.
8.  **Procesar Pago / Subir Comprobante**: Para Tarjeta, el flujo redirige o integra la pasarela segura; para Transferencia/Deuna, muestra información y provee el formulario para subir el comprobante.
9.  **Confirmación de Pago/Pedido**: Mensaje final según el estado de la transacción: "PAGADO" (notificación automática de la pasarela), "Aprobado" (manual), "Pendiente" o "Rechazado" (con opción a reintentar).
10. **Crear Cuenta (Opcional)**: Flujo de registro voluntario para el cliente posterior a la compra utilizando la información ya ingresada.

### Módulos del Administrador (Privado)
0.  **Login**: Autenticación segura para el panel administrativo.
1.  **Dashboard**: Resumen de ventas, pedidos pendientes/pagados y alertas de inventario.
2.  **Productos**: Listado y administración general de artículos.
3.  **Formulario de Producto**: Creación y edición de productos (con carga de imágenes).
4.  **Categorías**: Clasificación jerárquica de productos.
5.  **Inventario**: Control de existencias físicas y stock mínimo.
6.  **Pedidos**: Listado de pedidos recibidos agrupados por estado (Pendiente, Validando, PAGADO, Aprobado, Cancelado).
7.  **Detalle del Pedido**: Visualización de los datos del cliente, productos solicitados, comprobante de pago o detalles del pago por pasarela de pagos (transacción, referencia, logs), y controles para gestionar el estado de la orden.
8.  **Clientes**: Registro y consulta de clientes que han interactuado con el sistema.
9.  **Promociones**: Creación de cupones de descuento o promociones especiales.
10. **Reportes**: Estadísticas básicas de facturación y productos más vendidos.
11. **Usuarios**: Gestión de administradores y roles del sistema.
12. **Configuración**: Credenciales de pasarelas de pago, datos bancarios, números de contacto y configuraciones generales.

---

## 4. Estructura Interna Recomendada de Documentos

Para completar esta sección, se planean los siguientes documentos:
1.  `01-vision-scope.md`: Detalle profundo de la visión de negocio, metas financieras básicas y alcance delimitado.
2.  `02-glossary.md`: Glosario de términos (ej. Checkout inteligente, Deuna, pasarela de pago, webhook).

---

## 5. Dependencias con otros Documentos
*   **01-business**: Se alimenta de los objetivos generales de este documento para formular las reglas comerciales detalladas.
*   **02-requirements**: Detalla a nivel técnico el alcance de los módulos del cliente y del administrador aquí listados.
*   **11-decisions**: Contextualiza la decisión de integrar una pasarela de pago desacoplada mediante patrones Strategy y Adapter, y la compra sin registro obligatorio.

---

## 6. Observaciones de Inconsistencias
*   **Registro del Cliente**: El alcance del MVP original incluía "Registro e inicio de sesión" en el cliente, lo cual contradice la decisión inmutable de compra sin registro obligatorio y autenticación opcional post-compra. Se mantiene la indicación de que el registro es estrictamente opcional y se realiza al finalizar la transacción.
*   **WhatsApp**: Se menciona "Integración con WhatsApp" en algunas descripciones de alcance del MVP, pero no está detallado en los módulos funcionales del cliente. Se asume que se estructurará como un enlace de contacto directo en la cabecera/pie del sitio y no como un módulo interactivo del sistema.
