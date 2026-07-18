# Sección 11: Registro de Decisiones de Arquitectura (ADR) - Almacenes al Costo

## 1. Propósito de la Sección
Esta sección documenta formalmente las decisiones técnicas, funcionales y arquitectónicas clave tomadas a lo largo del ciclo de vida del proyecto. Cada Registro de Decisión de Arquitectura (ADR, por sus siglas en inglés) detalla el contexto, las alternativas evaluadas, los criterios de decisión y el impacto técnico, asegurando que el equipo de desarrollo no cambie decisiones ya establecidas y comprenda su justificación.

---

## 2. Objetivos
*   Mantener el histórico y la justificación de las decisiones inmutables tomadas para el MVP.
*   Evitar la re-evaluación constante de decisiones de diseño por parte de nuevos desarrolladores o agentes de IA.
*   Documentar el impacto y las implicaciones técnicas de cada decisión arquitectónica en el backend y frontend.

---

## 3. Estructura Interna Recomendada de Documentos

Esta carpeta contendrá los siguientes registros ADR estructurados:

### [01-adr-checkout-sin-cuenta.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/11-decisions/01-adr-checkout-sin-cuenta.md)
*   **Propósito**: Documentar la decisión de omitir el registro obligatorio de cuentas para el flujo de compra.
*   **Contenido esperado**:
    *   *Estado*: Aceptado (Inmutable).
    *   *Contexto*: Reducir la fricción en el embudo de ventas y aumentar la tasa de conversión en dispositivos móviles.
    *   *Decisión*: Permitir que el cliente complete el checkout ingresando únicamente sus datos de facturación y entrega, relegando la creación de cuenta como un paso opcional al finalizar la compra.
    *   *Consecuencias*: Necesidad de almacenar la información del cliente de forma directa en los pedidos, o crear clientes temporales en el sistema.

### [02-adr-pago-manual.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/11-decisions/02-adr-pago-manual.md)
*   **Propósito**: Documentar la decisión inicial de no integrar pasarelas de pago automáticas en la versión 1 y usar un sistema manual basado en comprobantes.
*   **Contenido esperado**:
    *   *Estado*: Superado por ADR 04.
    *   *Detalle*: Explicaba el uso inicial exclusivo de transferencias directas y Deuna para mitigar tiempos de aprobación comercial bancaria.

### [03-adr-tecnologias.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/11-decisions/03-adr-tecnologias.md)
*   **Propósito**: Documentar la selección del stack tecnológico (Laravel + PHP + MySQL + Blade + Bootstrap 5).
*   **Contenido esperado**:
    *   *Estado*: Aceptado (Inmutable).
    *   *Contexto*: Requisitos del servidor del cliente (despliegue local sencillo, conocimiento previo del stack, facilidad de mantenimiento).
    *   *Decisión*: Utilizar Laravel como framework monolítico de servidor, MySQL para la base de datos relacional y Blade/Bootstrap 5 para la interfaz de usuario.
    *   *Consecuencias*: Estructura limpia y fácil de desplegar bajo entornos locales XAMPP. Se descartan tecnologías SPA complejas (Vue/React) para optimizar el tiempo de desarrollo del MVP.

### [04-adr-integracion-pasarela.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/11-decisions/04-adr-integracion-pasarela.md)
*   **Propósito**: Documentar la decisión de integrar una pasarela de pagos automatizada mediante patrones Strategy y Adapter en V1.0.
*   **Contenido esperado**:
    *   *Estado*: Aceptado.
    *   *Contexto*: Impulsar las ventas del MVP incorporando pagos instantáneos con tarjeta de crédito/débito.
    *   *Decisión*: Adoptar una arquitectura de pagos desacoplada estructurada en base a la interfaz `PaymentGatewayInterface` y el servicio `PaymentService`, implementando adaptadores para Stripe, PayPhone, Datafast, Kushki y Manual, permitiendo intercambiar el proveedor de cobros sin alterar el checkout.
    *   *Consecuencias*: Los pagos con tarjeta son automáticos y descuentan el stock directamente tras webhooks validados, disminuyendo la carga de validación manual.

---

## 4. Dependencias con otros Documentos
*   **00-project/README.md**: Define las decisiones fundamentales que sustentan los objetivos y alcance del MVP del proyecto.
*   **01-business/01-business-rules.md**: Traduce los ADRs en reglas operativas obligatorias para el negocio.
*   **03-architecture/01-system-architecture.md**: Utiliza los ADRs tecnológicos para diseñar el desacoplamiento de las clases y la estructura del software.

---

## 5. Observaciones de Inconsistencias
*   *Modificación de decisiones*: Cualquier cambio a las decisiones registradas en esta sección alteraría el alcance del proyecto y violaría las reglas obligatorias del negocio. Por lo tanto, estos registros deben ser tratados como de solo lectura por parte de Codex u otros agentes de desarrollo.
