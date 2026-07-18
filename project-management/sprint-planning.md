# Planificación de Sprints — Almacenes al Costo

## Propósito del Documento
Definir la estructura, objetivo, alcance y entregables de cada uno de los cinco sprints del proyecto MVP. Este documento guía la conversión del Product Backlog en trabajo ejecutable en GitHub Projects.

> **Convenciones**:
> - **Duración de sprint**: 2 semanas cada uno.
> - **Duración total estimada**: 10 semanas (~2.5 meses).
> - **Ceremonias**: Daily standup (async), Sprint Review al final de cada sprint, Sprint Retrospective, Sprint Planning al inicio del siguiente.
> - **Herramienta de ejecución**: GitHub Projects (Ver [`github-project-structure.md`](./github-project-structure.md))

---

## Sprint 1 — Infraestructura, Autenticación y Configuración

| Campo | Detalle |
| :--- | :--- |
| **Número** | Sprint 1 |
| **Duración** | 2 semanas |
| **Épicas cubiertas** | EP-001 |
| **Milestone GitHub** | `M1: Infraestructura Base` |

### Objetivo del Sprint
> Dejar el entorno de desarrollo completamente configurado, el esquema de base de datos inicializado, el sistema de autenticación del administrador operativo, y los layouts base del cliente y del panel admin implementados con la paleta de diseño correcta.

### User Stories incluidas

| ID | User Story | Referencia Backlog |
| :- | :--- | :--- |
| US-001 | Configurar el proyecto Laravel con todas las dependencias | FT-001.1 |
| US-002 | Configurar el archivo `.env` con todas las variables del sistema | FT-001.1 |
| US-003 | Ejecutar todas las migraciones en orden | FT-001.1 |
| US-004 | Ejecutar los seeders iniciales | FT-001.1 |
| US-005 | Login del administrador con email y contraseña | FT-001.2 |
| US-006 | Proteger rutas `/admin/*` con middleware `auth` | FT-001.2 |
| US-007 | Logout del administrador de forma segura | FT-001.2 |
| US-008 | Layout base del cliente con paleta cálida | FT-001.3 |
| US-009 | Layout base del panel administrativo | FT-001.3 |
| US-010 | Configurar el sistema de colas (`database`) | FT-001.3 |

### Entregables
*   ✅ Proyecto Laravel funcionando en XAMPP local.
*   ✅ Base de datos con todas las tablas migradas y datos semilla.
*   ✅ Panel de login del administrador funcional y protegido.
*   ✅ Layout de cliente y admin con Bootstrap 5 y paleta de colores.
*   ✅ Cola de trabajos configurada.

### Dependencias
*   Ninguna. Punto de inicio del proyecto.

### Definición de Hecho aplicable
Ver [`definition-of-done.md`](./definition-of-done.md)

---

## Sprint 2 — Sitio Público: Catálogo y Navegación

| Campo | Detalle |
| :--- | :--- |
| **Número** | Sprint 2 |
| **Duración** | 2 semanas |
| **Épicas cubiertas** | EP-002 |
| **Milestone GitHub** | `M2: Sitio Público` |

### Objetivo del Sprint
> Implementar la experiencia completa de navegación del cliente: página de inicio, catálogo con filtros y búsqueda de texto, y vista de detalle de producto, con diseño responsivo y el enlace de WhatsApp Click-to-Chat en el footer.

### User Stories incluidas

| ID | User Story | Referencia Backlog |
| :- | :--- | :--- |
| US-011 | Página de inicio con banners y productos destacados | FT-002.1 |
| US-012 | Filtrar productos por categoría | FT-002.2 |
| US-013 | Búsqueda de productos por texto | FT-002.2 |
| US-014 | Ordenar productos por precio | FT-002.2 |
| US-015 | Vista de detalle con descripción, imágenes y precio | FT-002.3 |
| US-016 | Mostrar indicador de disponibilidad de stock | FT-002.3 |
| US-017 | Enlace WhatsApp Click-to-Chat en footer | FT-002.3 |

### Entregables
*   ✅ Página de inicio con carrusel de banners.
*   ✅ Catálogo filtrable y con búsqueda.
*   ✅ Detalle de producto con galería de imágenes.
*   ✅ Diseño responsivo aplicado en todas las vistas públicas.
*   ✅ Enlace de WhatsApp en footer.

### Dependencias
*   Sprint 1 completado (base de datos con productos seeded, layouts base).

---

## Sprint 3 — Carrito, Checkout y Módulo de Pagos

| Campo | Detalle |
| :--- | :--- |
| **Número** | Sprint 3 |
| **Duración** | 2 semanas |
| **Épicas cubiertas** | EP-003, EP-004, EP-005, EP-006 |
| **Milestone GitHub** | `M3: Flujo de Compra y Pagos` |

### Objetivo del Sprint
> Implementar el flujo completo de compra del cliente: desde agregar productos al carrito hasta completar el pago (por pasarela o manual), incluyendo la lógica de webhooks, reserva de stock, pantallas de retorno y el registro opcional post-compra.

> ⚠️ **Sprint más complejo del proyecto.** Concentra la lógica de negocio más crítica. Requiere especial atención a la seguridad y a la separación de responsabilidades definida en el SPEC.

### User Stories incluidas

| ID | User Story | Referencia Backlog |
| :- | :--- | :- |
| US-018 al US-022 | Gestión completa del carrito con persistencia | FT-003.1 |
| US-023 al US-028 | Formulario de checkout, creación de pedido, snapshot de precios | FT-004.1, FT-004.2 |
| US-029 | Selección del método de pago | FT-005.1 |
| US-030 al US-033 | Flujo completo de pago con tarjeta y pasarela | FT-005.2 |
| US-034 al US-036 | Flujo de pago manual con carga de comprobante | FT-005.3 |
| US-037 al US-039 | Pantallas de retorno (éxito, fallo, pendiente) | FT-005.4 |
| US-040 | Expiración automática de reservas de stock | FT-005.5 |
| US-041 al US-042 | Registro opcional post-compra | FT-006.1 |

### Entregables
*   ✅ Carrito funcional con `localStorage`.
*   ✅ Formulario de checkout validado en servidor.
*   ✅ Pedidos creados con número único y snapshot de precios.
*   ✅ `PaymentService`, `PaymentFactory` y al menos un adaptador de pasarela funcional.
*   ✅ Flujo de pago manual con carga y almacenamiento seguro de comprobante.
*   ✅ Endpoint de webhook con validación de firma e idempotencia.
*   ✅ Job de expiración de reservas configurado.
*   ✅ Pantallas de éxito, fallo y pendiente.
*   ✅ Registro post-compra funcional.

### Dependencias
*   Sprint 1 y Sprint 2 completados.
*   Credenciales de al menos una pasarela disponibles en `.env`.

---

## Sprint 4 — Panel Administrativo

| Campo | Detalle |
| :--- | :--- |
| **Número** | Sprint 4 |
| **Duración** | 2 semanas |
| **Épicas cubiertas** | EP-007, EP-008, EP-009 |
| **Milestone GitHub** | `M4: Panel Administrativo` |

### Objetivo del Sprint
> Implementar el panel administrativo completo: gestión del catálogo (productos, categorías, inventario), gestión de pedidos con flujos diferenciados para pagos manuales y pasarela, configuración de la tienda y usuarios administradores.

### User Stories incluidas

| ID | User Story | Referencia Backlog |
| :- | :--- | :- |
| US-043 | Dashboard con métricas rápidas | FT-007.1 |
| US-044 | CRUD de categorías | FT-007.2 |
| US-045 al US-046 | CRUD de productos con imágenes | FT-007.3 |
| US-047 al US-048 | Gestión de inventario y umbrales | FT-007.4 |
| US-049 | Gestión de promociones y cupones | FT-007.5 |
| US-050 | Listado y filtrado de pedidos | FT-008.1 |
| US-051 al US-054 | Detalle de pedidos, aprobación, rechazo y descarga de comprobante | FT-008.2 |
| US-055 | Configuración de tienda y pasarelas | FT-009.1 |
| US-056 al US-057 | Gestión de clientes y usuarios admin | FT-009.2 |
| US-058 | Exportación de reportes a Excel | FT-009.3 |

### Entregables
*   ✅ Dashboard con métricas visibles.
*   ✅ CRUDs de categorías, productos e inventario funcionales.
*   ✅ Vista de pedidos con flujo diferenciado (manual: visor + botones; pasarela: logs + sin botones de aprobación).
*   ✅ Botones de aprobación y rechazo con lógica de Events (`OrderApproved`).
*   ✅ Descarga segura de comprobantes vía `Storage::response()`.
*   ✅ Panel de configuración de la tienda operativo.

### Dependencias
*   Sprint 3 completado (pedidos y pagos generados).

---

## Sprint 5 — Testing, Seguridad y Despliegue

| Campo | Detalle |
| :--- | :--- |
| **Número** | Sprint 5 |
| **Duración** | 2 semanas |
| **Épicas cubiertas** | EP-010 |
| **Milestone GitHub** | `M5: QA y Entrega MVP` |

### Objetivo del Sprint
> Verificar que todos los controles de seguridad están implementados, ejecutar los casos de prueba definidos en el SPEC, corregir los defectos encontrados y desplegar el sistema en el entorno local para la revisión final del cliente.

### User Stories incluidas

| ID | User Story | Referencia Backlog |
| :- | :--- | :- |
| US-059 | Verificar todos los controles de seguridad (CSRF, XSS, SQL, PCI-DSS, webhooks) | FT-010.1 |
| US-060 | Ejecutar los 5 casos de prueba del SPEC y documentar resultados | FT-010.2 |
| US-061 | Implementar pruebas unitarias para `PaymentService` y adaptadores | FT-010.2 |
| US-062 | Despliegue en entorno local XAMPP para revisión del cliente | FT-010.3 |

### Entregables
*   ✅ Reporte de ejecución de los 5 casos de prueba con resultados.
*   ✅ Pruebas unitarias de `PaymentService` y al menos un adaptador.
*   ✅ Checklist de seguridad completado sin hallazgos pendientes.
*   ✅ Sistema desplegado y funcionando en entorno local.
*   ✅ Sesión de revisión con el cliente realizada.
*   ✅ **MVP V1.0 entregado.**

### Dependencias
*   Sprints 1 al 4 completados (funcionalidades 100% implementadas).

---

## Resumen de Sprints

| Sprint | Enfoque | Épicas | US | Milestone |
| :- | :--- | :--- | :- | :--- |
| 1 | Infraestructura + Auth | EP-001 | US-001 a US-010 | M1 |
| 2 | Sitio Público | EP-002 | US-011 a US-017 | M2 |
| 3 | Carrito + Checkout + Pagos | EP-003, EP-004, EP-005, EP-006 | US-018 a US-042 | M3 |
| 4 | Panel Administrativo | EP-007, EP-008, EP-009 | US-043 a US-058 | M4 |
| 5 | Testing + Deploy | EP-010 | US-059 a US-062 | M5 |
