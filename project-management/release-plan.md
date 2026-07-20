# Plan de Versiones (Release Plan) — Almacenes al Costo

## Propósito del Documento
Definir las versiones planificadas del sistema, el alcance de cada una y su relación con las épicas y el roadmap del proyecto. Cada versión representa un conjunto coherente y funcional de capacidades entregadas.

> **Fuente de referencia**: [`spec/12-roadmap/01-mvp-release.md`](../spec/12-roadmap/01-mvp-release.md) y [`spec/12-roadmap/02-future-scope.md`](../spec/12-roadmap/02-future-scope.md)

---

## Versión 1.0 — MVP (Producto Mínimo Viable)

| Campo | Detalle |
| :--- | :--- |
| **Versión** | 1.0 |
| **Nombre** | MVP — Almacenes al Costo en Producción |
| **Objetivo** | Lanzar la primera versión funcional y completa del sistema de tienda en línea con soporte para los tres métodos de pago definidos en el SPEC. |
| **Entorno objetivo** | Local (XAMPP) con posibilidad de despliegue en hosting compartido. |
| **Duración estimada** | 5 Sprints (Ver [`sprint-planning.md`](./sprint-planning.md)) |
| **Estado** | 🔵 En planificación |

### Capacidades incluidas en V1.0

| Épica | Capacidades entregadas |
| :--- | :--- |
| EP-001 | Entorno configurado, BD inicializada, autenticación admin, layouts base |
| EP-002 | Inicio, catálogo con filtros/búsqueda, detalle de producto |
| EP-003 | Carrito con persistencia en `localStorage` |
| EP-004 | Checkout sin login, generación de orden, snapshot de precios |
| EP-005 | Pago con tarjeta (pasarela), pago manual (transferencia/Deuna), webhooks, pantallas de retorno, expiración de reservas |
| EP-006 | Registro opcional post-compra |
| EP-007 | CRUD de productos, categorías e inventario, dashboard, promociones |
| EP-008 | Gestión de pedidos con flujo diferenciado (manual vs pasarela) |
| EP-009 | Configuración de tienda y pasarelas, gestión de usuarios admin |
| EP-010 | Verificación de seguridad, ejecución de casos de prueba, despliegue local |

### Criterios de Aceptación del MVP

*   El cliente puede completar una compra de punta a punta con cualquiera de los tres métodos de pago.
*   El administrador puede gestionar el catálogo, aprobar pedidos manuales y ver el historial de pedidos.
*   Los controles de seguridad documentados en `spec/08-security/` están todos implementados y verificados.
*   Los 5 casos de prueba definidos en `spec/09-testing/02-test-cases.md` pasan exitosamente.
*   El sistema responde correctamente en el entorno local XAMPP.

---

## Versión 1.1 — Mejoras Post-MVP

| Campo | Detalle |
| :--- | :--- |
| **Versión** | 1.1 |
| **Nombre** | Mejoras de Usabilidad y Operación |
| **Objetivo** | Incorporar mejoras menores basadas en el feedback del cliente tras el lanzamiento del MVP, sin cambiar la arquitectura del sistema. |
| **Entorno objetivo** | Producción (hosting). |
| **Estado** | ⚪ Pendiente — Definición de alcance tras revisión del MVP. |

### Capacidades candidatas para V1.1

> **Nota**: El alcance definitivo se define tras la revisión del cliente del MVP. Las siguientes son candidatas basadas en funcionalidades de menor prioridad del backlog.

*   Mejoras de rendimiento (optimización de consultas, caché de catálogo).
*   Panel de reportes con exportación a Excel (RFA-10 — prioridad 🟢).
*   Visualización de historial de pedidos para clientes registrados.
*   Ajustes de UX basados en feedback del cliente (flujos, textos, colores).
*   Configuración de producción HTTPS y optimización de assets.

---

## Versión 2.0 — Nuevas Funcionalidades

| Campo | Detalle |
| :--- | :--- |
| **Versión** | 2.0 |
| **Nombre** | Expansión del Ecosistema Comercial |
| **Objetivo** | Incorporar integraciones externas avanzadas que amplíen las capacidades operativas de la tienda. |
| **Entorno objetivo** | Producción (hosting dedicado o VPS). |
| **Estado** | ⚪ Pendiente — Planificación futura. |

### Capacidades planificadas para V2.0

> **Fuente**: [`spec/12-roadmap/02-future-scope.md`](../spec/12-roadmap/02-future-scope.md)

*   **Integración con Couriers Locales**: Cotización automática de fletes y generación de guías de despacho con Servientrega, Laar Courier u otras.
*   **Facturación Electrónica**: Emisión automatizada del XML y PDF firmado ante el SRI de Ecuador al procesarse el pago.
*   **Notificaciones por WhatsApp API**: Notificaciones automáticas de confirmación y despacho usando la API oficial de WhatsApp Business (Twilio u otro proveedor).

---

## Línea de Tiempo Visual

```
Sprint 1      Sprint 2      Sprint 3        Sprint 4          Sprint 5
│             │             │               │                 │
▼             ▼             ▼               ▼                 ▼
EP-001    EP-002        EP-003          EP-007            EP-010
Infra     Catálogo      EP-004          EP-008
Auth      Público       EP-005          EP-009
Layouts               Carrito         Panel Admin
                      Checkout
                      Pagos
                      EP-006

│─────────────────── V1.0 MVP ────────────────────────────────│

                                                          │──── V1.1 ────│

                                                                     │────── V2.0 ──────│
```
