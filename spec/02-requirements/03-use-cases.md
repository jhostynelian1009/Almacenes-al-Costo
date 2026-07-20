# Documento: Casos de Uso del Sistema

## 1. Objetivos del Documento
Describir detalladamente los flujos de interacción entre los diferentes actores y la plataforma en escenarios críticos de la aplicación.

## 2. Casos de Uso Principales

### CU-01: Proceso de Compra con Pago Manual (Transferencia o Deuna)
*   **Actor**: Cliente.
*   **Precondición**: El cliente tiene productos en el carrito de compras.
*   **Flujo Principal**:
    1. El cliente accede a la vista de Checkout.
    2. Completa sus datos personales e información de entrega y selecciona "Transferencia" o "Deuna".
    3. Confirma la creación del pedido.
    4. El sistema guarda la orden como "Pendiente" e inactiva/reserva el stock correspondiente.
    5. El sistema presenta las cuentas bancarias de la empresa o el QR de Deuna.
    6. El cliente sube la captura de pantalla o PDF de la transacción bancaria.
    7. El sistema almacena de forma segura el archivo del comprobante, lo vincula a la orden y cambia el estado del pedido a "Validando".
    8. El cliente visualiza un mensaje informando que su pedido está en proceso de verificación.

### CU-02: Validación Manual de Pago por Administrador
*   **Actor**: Administrador.
*   **Precondición**: Existen pedidos en estado "Validando".
*   **Flujo Principal**:
    1. El administrador ingresa al listado de Pedidos en el Panel Administrativo.
    2. Filtra por estado "Validando" y selecciona un pedido.
    3. Visualiza los datos de la orden y el visor de comprobante (imagen o PDF).
    4. Valida en la banca móvil corporativa el ingreso efectivo de los fondos y la coincidencia de montos.
    5. El administrador aprueba el pedido desde el panel.
    6. El sistema cambia el pedido a "Aprobado", deduce definitivamente las existencias de stock y despacha la notificación de entrega.

### CU-03: Proceso de Compra con Pago en Línea (Pasarela de Pagos)
*   **Actor**: Cliente.
*   **Precondición**: El cliente tiene productos en el carrito de compras.
*   **Flujo Principal**:
    1. El cliente accede al Checkout, completa sus datos y selecciona "Tarjeta (Pasarela)".
    2. El sistema guarda el pedido en estado "Pendiente" y realiza el bloqueo temporal del stock (15 minutos).
    3. El sistema redirige al cliente a la página de pago seguro de la pasarela (o carga un modal inline tokenizado).
    4. El cliente ingresa los datos de su tarjeta y confirma la transacción.
    5. La pasarela procesa el cobro y comunica el resultado al sistema (Webhook/Callback).
    6. El sistema procesa la respuesta:
        *   Si es Exitosa: Cambia el estado del pedido automáticamente a "PAGADO", consolida el stock restado del inventario de forma definitiva y redirige al cliente a la página de éxito.
        *   Si es Fallida: Libera el stock reservado y redirige al cliente a la página de rechazo con opciones para reintentar.

## 3. Referencias y Dependencias
*   [02-requirements/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/02-requirements/README.md)
*   [01-business/03-workflows.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/01-business/03-workflows.md)
