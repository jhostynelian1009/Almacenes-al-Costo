# Documento: Requerimientos Funcionales

> **Alineación vigente:** tarjeta/pasarela es futuro. El MVP exige checkout invitado, transferencia/Deuna, comprobante, revisión autorizada, observación e historial.

## 1. Objetivos del Documento
Especificar formalmente el listado de requerimientos funcionales requeridos para el desarrollo de los módulos de Cliente y Administrador.

## 2. Requerimientos de Módulos del Cliente (RFC)
*   **RFC-01 (Inicio)**: Mostrar un carrusel de banners destacados y grilla de productos destacados.
*   **RFC-02 (Catálogo)**: Permitir filtrado por categorías, búsqueda de texto y ordenamiento por precios.
*   **RFC-03 (Detalle)**: Visualizar descripción, especificaciones técnicas, precio con/sin descuento e imágenes secundarias.
*   **RFC-04 (Carrito)**: Permitir agregar, editar cantidades y eliminar productos con persistencia en `localStorage`.
*   **RFC-05 (Checkout)**: Formulario de captura de datos (Nombre, Cédula/RUC, Dirección, Teléfono, Correo).
*   **RFC-06 (Confirmación)**: Asignar número correlativo de pedido único al procesar el checkout.
*   **RFC-07 (Método de Pago)**: Permitir al cliente elegir su método de pago preferido (Tarjeta de crédito/débito, Transferencia Bancaria, Deuna) durante el Checkout.
*   **RFC-08 (Procesar Pago)**: 
    *   *Tarjeta*: Redireccionar al flujo seguro de la pasarela de pago o integrar formulario tokenizado en línea.
    *   *Transferencia / Deuna*: Mostrar datos de cuentas bancarias de la tienda o código QR de Deuna y proveer un selector de archivos (PDF/JPG/PNG) para cargar la captura del comprobante.
*   **RFC-09 (Pantallas de Confirmación/Retorno)**:
    *   *Pago Exitoso*: Mostrar detalles de la orden con estado "PAGADO" (si fue pasarela) y opción de Crear Cuenta.
    *   *Pago Fallido*: Mostrar pantalla de error en transacción con botón para reintentar el cobro.
    *   *Pago Pendiente*: Mostrar estado en proceso (esperando respuesta de la pasarela o validación manual) e instrucciones.
*   **RFC-10 (Crear Cuenta)**: Permitir registrar una contraseña en la pantalla de confirmación para guardar el perfil de cliente.

## 3. Requerimientos de Módulos del Administrador (RFA)
*   **RFA-00 (Login)**: Formulario de autenticación seguro para ingresar al panel de administración.
*   **RFA-01 (Dashboard)**: Métricas rápidas (ventas del mes, pedidos validando, alertas de inventario bajo).
*   **RFA-02 (Productos)**: Lista paginada con buscador, edición rápida y eliminación lógica.
*   **RFA-03 (Formulario)**: Carga de datos de producto con subida de múltiples fotos de soporte.
*   **RFA-04 (Categorías)**: Gestionar un árbol de categorías y subcategorías de múltiples niveles para organizar el catálogo.
    * Una categoría principal tiene `parent_id = NULL`; una subcategoría referencia opcionalmente a una categoría padre y una categoría puede tener múltiples subcategorías.
    * `name` y `slug` son únicos. `description`, `image` e `icon` son opcionales.
    * Una categoría no puede ser su propio padre y la aplicación debe impedir ciclos jerárquicos.
    * Una categoría inactiva no se muestra en el sitio público.
    * No se permite eliminar una categoría que tenga subcategorías o productos asociados.
    * Las categorías públicas se ordenan primero por `display_order` y después por `name`.
*   **RFA-05 (Inventario)**: Registro de stock físico, umbrales mínimos de alarma e historial de movimientos.
*   **RFA-06 (Pedidos)**: Tabla de pedidos clasificada por estado (Pendiente, Validando, PAGADO, Aprobado, Cancelado).
*   **RFA-07 (Detalle Pedido)**: Visualizador de la información de la orden y del pago. Si es manual, mostrar visor del comprobante cargado y botones para aprobar o rechazar con campo de observaciones. Si es pasarela, mostrar ID de transacción, logs de webhooks, monto liquidado y deshabilitar botones de aprobación manual.
*   **RFA-08 (Clientes)**: Visualizar base de datos de compradores registrados e invitados.
*   **RFA-09 (Promociones)**: Registro de cupones de descuento válidos para compras en checkout.
*   **RFA-10 (Reportes)**: Exportación simple a Excel y gráficos básicos de ventas por categorías.
*   **RFA-11 (Usuarios)**: Gestión de administradores del sistema con asignación básica de roles.
*   **RFA-12 (Configuración)**: Editar datos de contacto de la tienda, datos de cuenta bancaria, código QR de Deuna y llaves de acceso/API de las pasarelas de pago configuradas.

## 4. Referencias y Dependencias
*   [02-requirements/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/02-requirements/README.md)
*   [06-backend/02-controllers.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/06-backend/02-controllers.md)
