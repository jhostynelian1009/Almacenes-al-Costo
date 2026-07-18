# ADR 04: Integración Desacoplada de Pasarela de Pagos en el MVP

**Estado**: Aprobado / Aceptado

## 1. Contexto y Problema
Para maximizar las ventas e impulsar la tasa de conversión en el lanzamiento de la plataforma de **Almacenes al Costo**, se requiere integrar el cobro con Tarjetas de Crédito y Débito en línea desde la versión MVP (V1.0). 
Existen múltiples proveedores de pasarela de pago viables en Ecuador (Stripe, PayPhone, Datafast, Kushki). Cada uno posee estructuras de API, firmas de seguridad, mecanismos de redirección (redirección completa, modales inline) y formatos de webhooks completamente disímiles. 
Acoplar el controlador del Checkout directamente a la API de un proveedor específico generaría una fuerte dependencia tecnológica (Vendor Lock-in) y haría extremadamente costoso migrar a otro proveedor en el futuro.

## 2. Decisión Tomada
Implementar una **Arquitectura de Pagos Desacoplada** utilizando los patrones de diseño **Strategy** y **Adapter**.
*   **Contrato Unificado**: Definir la interfaz `PaymentGatewayInterface` que obligue a los adaptadores a implementar la inicialización (`initializePayment`), el retorno síncrono (`handleCallback`) y la notificación asíncrona (`handleWebhook`).
*   **Despacho Dinámico**: Crear un servicio central `PaymentService` que reciba el método de pago seleccionado por el cliente, instancie dinámicamente el adaptador correcto (ej. `StripeAdapter`, `PayPhoneAdapter`, `ManualPaymentAdapter`) y orqueste el flujo.
*   **Base de Datos No Acoplada**: Registrar los cobros en una tabla genérica `payments`, guardando los detalles técnicos y respuestas en formato JSON en `payment_transactions` para auditoría, evitando columnas específicas de proveedores en la tabla de pedidos (`orders`).
*   **Tratamiento de Pagos Manuales**: El flujo de transferencia y Deuna se implementa como un adaptador más (`ManualPaymentAdapter`), de modo que el Checkout los trate de forma homogénea.

## 3. Consecuencias
*   *Positivo (Escalabilidad)*: Permite añadir nuevos proveedores de pago escribiendo únicamente un nuevo adaptador que implemente la interfaz, sin alterar el flujo del checkout ni las vistas generales.
*   *Positivo (Mantenimiento)*: Centraliza la lógica de auditoría, logs de errores de pasarelas, e idempotencia (evitando cobros dobles) en un único punto del sistema (`PaymentService`).
*   *Positivo (Seguridad)*: Cumple con regulaciones PCI-DSS al aislar el tratamiento de datos sensibles de tarjetas exclusivamente en la interfaz de pago provista por el adaptador de cada pasarela.
*   *Negativo (Esfuerzo Técnico)*: Requiere un mayor nivel de abstracción inicial en Laravel y el desarrollo estructurado de DTOs (`PaymentResponse`) para estandarizar las respuestas de las APIs externas.

## 4. Referencias y Dependencias
*   [03-architecture/01-system-architecture.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/03-architecture/01-system-architecture.md)
*   [06-backend/04-services-helpers.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/06-backend/04-services-helpers.md)
*   [05-database/02-schema-definition.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/05-database/02-schema-definition.md)
