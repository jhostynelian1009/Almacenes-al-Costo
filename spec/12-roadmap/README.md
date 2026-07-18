# Sección 12: Plan de Evolución (Roadmap) - Almacenes al Costo

## 1. Propósito de la Sección
Esta sección detalla las etapas de desarrollo y los hitos del proyecto. Establece la ruta para la entrega de la versión actual (MVP) y define las bases funcionales y técnicas para la incorporación de características avanzadas en futuras versiones del sistema, garantizando que el diseño actual soporte la evolución del negocio.

---

## 2. Objetivos
*   Definir las fases de desarrollo y entrega del Producto Mínimo Viable (MVP).
*   Definir las fases de desarrollo y entrega del Producto Mínimo Viable (MVP).
*   Planificar las integraciones técnicas futuras (couriers locales de entrega, facturación electrónica avanzada, notificaciones oficiales automáticas).
*   Garantizar la compatibilidad hacia adelante de las decisiones arquitectónicas actuales.

---

## 3. Estructura Interna Recomendada de Documentos

Esta carpeta contendrá los siguientes planes de trabajo:

### [01-mvp-release.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/12-roadmap/01-mvp-release.md)
*   **Propósito**: Detallar el plan de despliegue y lanzamiento del MVP de Almacenes al Costo.
*   **Contenido esperado**:
    *   *Fase 1: Configuración de Base de Datos y CRUDs Básicos (Admin)*: Categorías, productos e inventario.
    *   *Fase 2: Interfaz Pública del Cliente*: Catálogo, carrito y Checkout.
    *   *Fase 3: Pasarela de Pagos e Integración*: Contrato de pagos, adaptadores (Stripe, Payphone, etc.), callback, webhooks y vistas de retorno del cliente.
    *   *Fase 4: Panel Administrativo, Seguridad y Pruebas*: Bitácora de pedidos diferenciada, validación de firmas de webhooks, idempotencia, carga segura de comprobantes manuales y test cases completos.

### [02-future-scope.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/12-roadmap/02-future-scope.md)
*   **Propósito**: Describir los requerimientos de las fases posteriores al MVP.
*   **Contenido esperado**:
    *   *Integración con Couriers Locales*: Conexión con transportadoras locales para fletes dinámicos y despacho automatizado.
    *   *Automatización de Notificaciones*: Envío automático de notificaciones de despacho y confirmaciones utilizando la API de WhatsApp Business.
    *   *Facturación Electrónica*: Emisión automatizada del XML y PDF de facturas firmadas ante el SRI al procesarse el pago.

---

## 4. Dependencias con otros Documentos
*   **00-project/README.md**: Mantiene concordancia con el alcance inicial delimitado para el MVP.
*   **03-architecture/01-system-architecture.md**: Utiliza la planificación del roadmap para asegurar que los componentes de software (como el módulo de pago) sean modulares y desacoplados.
*   **11-decisions/README.md**: Los hitos de desarrollo se organizan respetando las decisiones tomadas en los ADRs (ej. pasarela de pagos desacoplada en V1.0).

---

## 5. Observaciones de Inconsistencias
*   *Priorización del Catálogo*: Debido a que el negocio requiere optimizar las ventas, se debe priorizar el desarrollo y estabilidad del Catálogo y el Checkout Inteligente en el MVP antes de iniciar el desarrollo de los módulos complementarios de Promociones y Reportes del Administrador.
