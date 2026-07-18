# Documento: Especificaciones de Pantalla por Módulo

## 1. Objetivos del Documento
Especificar los elementos UI, formularios, inputs y comportamientos esperados en cada una de las interfaces de cliente y administración.

## 2. Especificación de Pantallas Clave

### Cliente: 05. Checkout Inteligente
*   **Secciones**:
    1. Formulario de Datos Personales (Nombre, Cédula/RUC, Teléfono, Correo).
    2. Dirección de Entrega (Provincia, Ciudad, Calle Principal, Calle Secundaria, Referencia).
    3. Selector de Método de Pago:
        *   *Opción 1: Tarjeta (Pasarela)*. Iconos de Visa/Mastercard. Advierte sobre redirección segura.
        *   *Opción 2: Transferencia Bancaria*. Muestra brevemente que requerirá subir comprobante.
        *   *Opción 3: Deuna*. Muestra que requerirá escanear QR y subir comprobante.
    4. Resumen de la Compra (Artículos con miniaturas, subtotal, IVA, cupón de descuento, total).
*   **Validaciones**: Frontend y backend estrictos. Al hacer clic en "Generar Pedido", se deshabilita el botón con un spinner para evitar solicitudes duplicadas. Si es Tarjeta, se inicia la reserva de stock y se redirecciona.

### Cliente: 05a. Pantalla de Pago Seguro (Tarjeta)
*   **Elementos**:
    *   Contenedor seguro (iframe de pasarela, checkout modal de Stripe/PayPhone o redirección externa).
    *   Indicador visual de conexión segura (candado HTTPS) y logotipo de Almacenes al Costo.
    *   Botón de retorno seguro para cancelar la transacción (libera stock y vuelve al checkout).

### Cliente: 06. Pantallas de Confirmación y Retorno
*   **Caso A: Pago Exitoso**
    *   *Estilo*: Fondo crema, tarjeta marfil con bordes redondeados y check verde animado.
    *   *Información*: Nro. de Pedido, Monto pagado por Pasarela (estado: "PAGADO") o Estado: "Validando" (si es manual).
    *   *Sección opcional*: Caja para ingresar contraseña y crear cuenta heredando los datos del Checkout.
*   **Caso B: Pago Rechazado**
    *   *Estilo*: Alerta roja con icono de advertencia.
    *   *Información*: Motivo del rechazo de la tarjeta (si la pasarela lo reporta).
    *   *Acciones*: Botón de "Intentar de nuevo" (redirige al Checkout con los productos conservados en el carrito) o "Cambiar método de pago".
*   **Caso C: Pago Pendiente**
    *   *Información*: Mensaje indicando que la transacción está en revisión por la pasarela de pagos. Muestra instrucciones para no duplicar el pago y esperar confirmación por correo.

### Administrador: 07. Detalle de Pedido (Gestión)
*   **Secciones**:
    1. Datos del Cliente, Dirección de Envío y Método de Pago seleccionado.
    2. Detalle de Productos e Importe Total.
    3. Panel de Pago:
        *   *Si es manual (Transferencia/Deuna)*: Muestra visor del comprobante PDF/JPG/PNG subido, campo para ingresar observaciones y botones "Aprobar Pedido" (pasa a Aprobado y resta stock) y "Rechazar Pedido" (pasa a Cancelado).
        *   *Si es pasarela (Tarjeta)*: Muestra estado "PAGADO", ID de transacción de pasarela, fecha/hora exacta del cobro, logs de eventos de webhook y DTO de respuesta. Inhabilita botones de aprobación manual.

## 3. Referencias y Dependencias
*   [04-ui-ux/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/04-ui-ux/README.md)
*   [02-requirements/01-functional-requirements.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/02-requirements/01-functional-requirements.md)
*   [07-frontend/01-blade-views.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/07-frontend/01-blade-views.md)
