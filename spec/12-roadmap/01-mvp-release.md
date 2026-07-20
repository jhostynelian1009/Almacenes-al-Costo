# Documento: Fases de Entrega del MVP

## 1. Objetivos del Documento
Estructurar el plan de entregas funcionales secuenciales necesarias para completar el desarrollo y despliegue del MVP.

## 2. Hitos y Fases de Desarrollo
*   **Hito 1 (Backend y Datos)**:
    *   Creación de migraciones, seeders y modelos de datos.
    *   Implementación de CRUDs administrativos de Productos, Categorías e Inventario.
*   **Hito 2 (Cliente y Ventas)**:
    *   Diseño responsivo del Catálogo de Productos y Vista Detallada.
    *   Lógica del Carrito de Compras en Javascript local.
    *   Implementación de la API de creación de pedidos en el Checkout.
*   **Hito 3 (Pasarela de Pagos e Integración)**:
    *   Definición de `PaymentGatewayInterface` y desarrollo del `PaymentService` central.
    *   Implementación de adaptadores concretos (`StripeAdapter`, `PayPhoneAdapter`, `DatafastAdapter`, `KushkiAdapter`, `ManualPaymentAdapter`).
    *   Configuración de rutas de pago, callback de pasarelas y endpoint público de Webhooks.
    *   Desarrollo de las vistas de pago del cliente (puente de pago, éxito, rechazado, pendiente).
    *   Implementación del bloqueo temporal de stock en inventarios (15 minutos).
*   **Hito 4 (Validación, Seguridad y Despliegue)**:
    *   Módulo administrativo de listado y detalle de pedidos, con visualización diferenciada para pasarela y carga de comprobantes manuales.
    *   Implementación de medidas de seguridad: firma de webhooks, tabla `webhook_logs` para control de idempotencia y validación MIME de comprobantes.
    *   Ejecución de Casos de Prueba (01 al 05) en ambiente local.
    *   Ajuste final de la paleta gráfica cálida (Crema/Naranja/Durazno) en las vistas Blade.

## 3. Referencias y Dependencias
*   [12-roadmap/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/12-roadmap/README.md)
*   [00-project/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/00-project/README.md)
