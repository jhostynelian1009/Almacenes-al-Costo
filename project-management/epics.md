# Épicas del Proyecto — Almacenes al Costo

## Propósito del Documento
Identificar y describir todas las épicas del proyecto. Cada épica agrupa un conjunto coherente de funcionalidades relacionadas que, en conjunto, entregan valor al negocio. Las épicas son el nivel más alto de descomposición del trabajo antes de las Features y User Stories.

> **Fuente de información**: Toda la información aquí documentada proviene de [`spec/02-requirements/01-functional-requirements.md`](../spec/02-requirements/01-functional-requirements.md) y [`spec/01-business/01-business-rules.md`](../spec/01-business/01-business-rules.md).

---

## Catálogo de Épicas

### EP-001 — Infraestructura y Configuración Base

| Campo | Detalle |
| :--- | :--- |
| **Código** | EP-001 |
| **Nombre** | Infraestructura y Configuración Base |
| **Objetivo** | Establecer el entorno de desarrollo, la base de datos, la autenticación administrativa y las configuraciones iniciales del sistema. |
| **Alcance** | Instalación de Laravel, migraciones iniciales, seeders de datos base, autenticación del administrador, configuración de variables de entorno y estructura de directorios. |
| **Dependencias** | Ninguna. Punto de inicio del proyecto. |
| **Módulos relacionados** | RFA-00 (Login Admin), `spec/10-deployment/01-env-variables.md`, `spec/05-database/04-seeders-migrations.md` |
| **Prioridad** | 🔴 Crítica |
| **Sprint recomendado** | Sprint 1 |
| **Referencias SPEC** | [`spec/03-architecture/03-directory-structure.md`](../spec/03-architecture/03-directory-structure.md), [`spec/10-deployment/01-env-variables.md`](../spec/10-deployment/01-env-variables.md), [`spec/08-security/01-auth-authorization.md`](../spec/08-security/01-auth-authorization.md) |

---

### EP-002 — Catálogo Público de Productos

| Campo | Detalle |
| :--- | :--- |
| **Código** | EP-002 |
| **Nombre** | Catálogo Público de Productos |
| **Objetivo** | Proveer al cliente una experiencia de navegación fluida y atractiva para descubrir y explorar el catálogo de productos disponibles. |
| **Alcance** | Página de inicio, catálogo con filtros y búsqueda, vista de detalle de producto, diseño responsivo con la paleta cálida definida. |
| **Dependencias** | EP-001 (base de datos y productos seeded). |
| **Módulos relacionados** | RFC-01 (Inicio), RFC-02 (Catálogo), RFC-03 (Detalle de Producto) |
| **Prioridad** | 🔴 Alta |
| **Sprint recomendado** | Sprint 2 |
| **Referencias SPEC** | [`spec/02-requirements/01-functional-requirements.md`](../spec/02-requirements/01-functional-requirements.md), [`spec/04-ui-ux/03-screen-specs.md`](../spec/04-ui-ux/03-screen-specs.md), [`spec/07-frontend/03-bootstrap-custom.md`](../spec/07-frontend/03-bootstrap-custom.md) |

---

### EP-003 — Carrito de Compras

| Campo | Detalle |
| :--- | :--- |
| **Código** | EP-003 |
| **Nombre** | Carrito de Compras |
| **Objetivo** | Permitir al cliente seleccionar, gestionar y persistir productos en el carrito de compras antes de proceder al checkout. |
| **Alcance** | Agregar productos al carrito, editar cantidades, eliminar ítems, persistencia en `localStorage`, visualización del resumen y acceso al checkout. |
| **Dependencias** | EP-002 (catálogo de productos funcional). |
| **Módulos relacionados** | RFC-04 (Carrito) |
| **Prioridad** | 🔴 Alta |
| **Sprint recomendado** | Sprint 3 |
| **Referencias SPEC** | [`spec/02-requirements/01-functional-requirements.md`](../spec/02-requirements/01-functional-requirements.md), [`spec/07-frontend/02-javascript-modules.md`](../spec/07-frontend/02-javascript-modules.md) |

---

### EP-004 — Checkout y Captura de Datos del Cliente

| Campo | Detalle |
| :--- | :--- |
| **Código** | EP-004 |
| **Nombre** | Checkout y Captura de Datos del Cliente |
| **Objetivo** | Capturar los datos del cliente y registrar el pedido en el sistema sin requerir autenticación previa. |
| **Alcance** | Formulario de datos personales y de envío, generación del número de orden único, registro del pedido en BD con estado `pending_payment`, vaciado del carrito. |
| **Dependencias** | EP-003 (carrito funcional), EP-001 (BD disponible). |
| **Módulos relacionados** | RFC-05 (Checkout), RFC-06 (Confirmación), RN-01, RN-07 |
| **Prioridad** | 🔴 Alta |
| **Sprint recomendado** | Sprint 3 |
| **Referencias SPEC** | [`spec/01-business/01-business-rules.md`](../spec/01-business/01-business-rules.md), [`spec/06-backend/02-controllers.md`](../spec/06-backend/02-controllers.md), [`spec/01-business/03-workflows.md`](../spec/01-business/03-workflows.md) |

---

### EP-005 — Módulo de Pagos

| Campo | Detalle |
| :--- | :--- |
| **Código** | EP-005 |
| **Nombre** | Módulo de Pagos |
| **Objetivo** | Implementar el sistema de pago desacoplado que soporte los tres métodos del MVP: Tarjeta de crédito/débito (pasarela), Transferencia bancaria y Deuna (pagos manuales). |
| **Alcance** | Selección de método de pago, integración con pasarelas (Stripe, PayPhone, Datafast, Kushki) mediante el patrón Strategy/Adapter, flujo de pago manual con carga de comprobante, reserva temporal de stock, pantallas de retorno (éxito, fallo, pendiente), webhooks con validación de firma e idempotencia. |
| **Dependencias** | EP-004 (pedido creado con `order_number`), EP-001 (credenciales de pasarelas en `settings`). |
| **Módulos relacionados** | RFC-07, RFC-08, RFC-09, RN-02, RN-03, RN-04, RN-06, RN-07, RN-08 |
| **Prioridad** | 🔴 Crítica |
| **Sprint recomendado** | Sprint 3 |
| **Referencias SPEC** | [`spec/03-architecture/01-system-architecture.md`](../spec/03-architecture/01-system-architecture.md), [`spec/03-architecture/04-webhooks.md`](../spec/03-architecture/04-webhooks.md), [`spec/06-backend/04-services-helpers.md`](../spec/06-backend/04-services-helpers.md), [`spec/08-security/02-upload-security.md`](../spec/08-security/02-upload-security.md) |

---

### EP-006 — Registro Opcional Post-Compra

| Campo | Detalle |
| :--- | :--- |
| **Código** | EP-006 |
| **Nombre** | Registro Opcional Post-Compra |
| **Objetivo** | Ofrecer al cliente la posibilidad de crear una cuenta al finalizar su compra, heredando los datos del checkout. |
| **Alcance** | Formulario de creación de contraseña en la pantalla de confirmación, vinculación del pedido al nuevo usuario, inicio de sesión automático. |
| **Dependencias** | EP-004 (pedido registrado), EP-005 (pago completado o comprobante subido). |
| **Módulos relacionados** | RFC-10 (Crear Cuenta), RN-05 |
| **Prioridad** | 🟡 Media |
| **Sprint recomendado** | Sprint 3 |
| **Referencias SPEC** | [`spec/01-business/01-business-rules.md`](../spec/01-business/01-business-rules.md), [`spec/06-backend/02-controllers.md`](../spec/06-backend/02-controllers.md) |

---

### EP-007 — Panel Administrativo: Catálogo y Operaciones

| Campo | Detalle |
| :--- | :--- |
| **Código** | EP-007 |
| **Nombre** | Panel Administrativo: Catálogo y Operaciones |
| **Objetivo** | Proveer al administrador las herramientas para gestionar el catálogo de productos, categorías, inventario y promociones. |
| **Alcance** | CRUDs de productos, categorías e inventario; carga de imágenes de productos; alertas de stock mínimo; gestión de promociones y cupones de descuento; dashboard con métricas. |
| **Dependencias** | EP-001 (autenticación admin). |
| **Módulos relacionados** | RFA-01 (Dashboard), RFA-02 (Productos), RFA-03 (Formulario), RFA-04 (Categorías), RFA-05 (Inventario), RFA-09 (Promociones) |
| **Prioridad** | 🔴 Alta |
| **Sprint recomendado** | Sprint 4 |
| **Referencias SPEC** | [`spec/02-requirements/01-functional-requirements.md`](../spec/02-requirements/01-functional-requirements.md), [`spec/06-backend/01-routes-map.md`](../spec/06-backend/01-routes-map.md) |

---

### EP-008 — Panel Administrativo: Gestión de Pedidos

| Campo | Detalle |
| :--- | :--- |
| **Código** | EP-008 |
| **Nombre** | Panel Administrativo: Gestión de Pedidos |
| **Objetivo** | Permitir al administrador visualizar, revisar y gestionar todos los pedidos del sistema, con flujos diferenciados para pagos manuales y pagos por pasarela. |
| **Alcance** | Listado de pedidos con filtros por estado, vista de detalle diferenciada (visor de comprobante para pagos manuales / bitácora de transacciones para pasarela), botones de aprobación y rechazo para pagos manuales. |
| **Dependencias** | EP-005 (pedidos con pagos generados), EP-007 (panel admin operativo). |
| **Módulos relacionados** | RFA-06 (Pedidos), RFA-07 (Detalle de Pedido) |
| **Prioridad** | 🔴 Alta |
| **Sprint recomendado** | Sprint 4 |
| **Referencias SPEC** | [`spec/06-backend/02-controllers.md`](../spec/06-backend/02-controllers.md), [`spec/01-business/01-business-rules.md`](../spec/01-business/01-business-rules.md) |

---

### EP-009 — Panel Administrativo: Configuración y Administración

| Campo | Detalle |
| :--- | :--- |
| **Código** | EP-009 |
| **Nombre** | Panel Administrativo: Configuración y Administración |
| **Objetivo** | Permitir al administrador gestionar la configuración de la tienda, datos bancarios, integraciones de pasarelas y usuarios del sistema. |
| **Alcance** | Edición de datos de la tienda, cuentas bancarias, código QR de Deuna, llaves de pasarelas, gestión de usuarios administradores, visualización de base de clientes registrados. |
| **Dependencias** | EP-001 (estructura base), EP-007 (panel admin). |
| **Módulos relacionados** | RFA-08 (Clientes), RFA-10 (Reportes), RFA-11 (Usuarios), RFA-12 (Configuración) |
| **Prioridad** | 🟡 Media |
| **Sprint recomendado** | Sprint 4 |
| **Referencias SPEC** | [`spec/02-requirements/01-functional-requirements.md`](../spec/02-requirements/01-functional-requirements.md), [`spec/10-deployment/01-env-variables.md`](../spec/10-deployment/01-env-variables.md) |

---

### EP-010 — Seguridad, Testing y Despliegue

| Campo | Detalle |
| :--- | :--- |
| **Código** | EP-010 |
| **Nombre** | Seguridad, Testing y Despliegue |
| **Objetivo** | Verificar la seguridad del sistema, ejecutar todos los casos de prueba definidos y desplegar el MVP en el entorno local (XAMPP) para revisión del cliente. |
| **Alcance** | Ejecución de los 5 casos de prueba del SPEC, validación de controles de seguridad (CSRF, XSS, SQL Injection, PCI-DSS, webhooks), configuración de colas, revisión final de la paleta visual, despliegue local. |
| **Dependencias** | EP-001 al EP-009 completados. |
| **Módulos relacionados** | Todas las secciones de seguridad y testing del SPEC. |
| **Prioridad** | 🔴 Alta (Entrega final) |
| **Sprint recomendado** | Sprint 5 |
| **Referencias SPEC** | [`spec/08-security/02-upload-security.md`](../spec/08-security/02-upload-security.md), [`spec/09-testing/02-test-cases.md`](../spec/09-testing/02-test-cases.md), [`spec/10-deployment/02-deployment-guide.md`](../spec/10-deployment/02-deployment-guide.md) |
