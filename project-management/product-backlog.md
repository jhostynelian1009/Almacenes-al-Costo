# Product Backlog — Almacenes al Costo

## Propósito del Documento
Registrar el inventario completo de trabajo del proyecto organizado jerárquicamente. Toda la información aquí contenida es extraída directamente del SPEC. Para el detalle técnico de cada funcionalidad, consultar la referencia al SPEC correspondiente.

> **Jerarquía**: `EPIC → FEATURE → USER STORY → TASK`
> **Prioridad**: 🔴 Alta — 🟡 Media — 🟢 Baja
> **Estimación**: Puntos de historia (se asignarán en el Sprint Planning de cada ciclo)

---

## EP-001 — Infraestructura y Configuración Base

### FT-001.1 — Configuración del Entorno de Desarrollo

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-001 | Como desarrollador, quiero configurar el proyecto Laravel con todas las dependencias, para tener el entorno listo. | 🔴 | [`spec/10-deployment/02-deployment-guide.md`](../spec/10-deployment/02-deployment-guide.md) |
| US-002 | Como desarrollador, quiero configurar el archivo `.env` con todas las variables del sistema, para conectar la BD y las pasarelas. | 🔴 | [`spec/10-deployment/01-env-variables.md`](../spec/10-deployment/01-env-variables.md) |
| US-003 | Como desarrollador, quiero ejecutar todas las migraciones en orden, para inicializar el esquema de base de datos. | 🔴 | [`spec/05-database/04-seeders-migrations.md`](../spec/05-database/04-seeders-migrations.md) |
| US-004 | Como desarrollador, quiero ejecutar los seeders iniciales, para poblar datos de configuración y un administrador por defecto. | 🔴 | [`spec/05-database/04-seeders-migrations.md`](../spec/05-database/04-seeders-migrations.md) |

### FT-001.2 — Autenticación del Administrador

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-005 | Como administrador, quiero iniciar sesión con email y contraseña, para acceder al panel de administración de forma segura. | 🔴 | [`spec/08-security/01-auth-authorization.md`](../spec/08-security/01-auth-authorization.md) |
| US-006 | Como sistema, quiero proteger todas las rutas `/admin/*` con middleware `auth`, para que solo el administrador autenticado pueda acceder. | 🔴 | [`spec/06-backend/01-routes-map.md`](../spec/06-backend/01-routes-map.md) |
| US-007 | Como administrador, quiero cerrar sesión de forma segura, para proteger el panel tras terminar mi trabajo. | 🔴 | [`spec/08-security/01-auth-authorization.md`](../spec/08-security/01-auth-authorization.md) |

### FT-001.3 — Layouts y Sistema de Diseño Base

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-008 | Como desarrollador, quiero implementar el layout base del cliente (`layouts/app.blade.php`) con la paleta cálida, para que todas las vistas del cliente hereden el diseño. | 🔴 | [`spec/07-frontend/03-bootstrap-custom.md`](../spec/07-frontend/03-bootstrap-custom.md) |
| US-009 | Como desarrollador, quiero implementar el layout base del admin (`layouts/admin.blade.php`), para que todas las vistas admin hereden la estructura del panel. | 🔴 | [`spec/07-frontend/01-blade-views.md`](../spec/07-frontend/01-blade-views.md) |
| US-010 | Como desarrollador, quiero configurar el sistema de colas (`QUEUE_CONNECTION=database`), para que los Jobs asíncronos puedan ejecutarse. | 🟡 | [`spec/06-backend/06-jobs.md`](../spec/06-backend/06-jobs.md) |

---

## EP-002 — Catálogo Público de Productos

### FT-002.1 — Página de Inicio

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-011 | Como cliente, quiero ver una página de inicio con banners destacados y productos destacados, para descubrir rápidamente las ofertas principales. | 🔴 | RFC-01, [`spec/04-ui-ux/03-screen-specs.md`](../spec/04-ui-ux/03-screen-specs.md) |

### FT-002.2 — Catálogo y Búsqueda

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-012 | Como cliente, quiero filtrar productos por categoría, para encontrar rápidamente lo que busco. | 🔴 | RFC-02 |
| US-013 | Como cliente, quiero buscar productos por texto, para encontrar un artículo específico sin navegar el catálogo completo. | 🔴 | RFC-02 |
| US-014 | Como cliente, quiero ordenar productos por precio (ascendente/descendente), para comparar opciones dentro de mi presupuesto. | 🟡 | RFC-02 |

### FT-002.3 — Vista de Detalle de Producto

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-015 | Como cliente, quiero ver la descripción completa, imágenes y precio de un producto, para tomar una decisión de compra informada. | 🔴 | RFC-03 |
| US-016 | Como cliente, quiero ver si hay stock disponible antes de agregar al carrito, para evitar decepciones. | 🟡 | [`spec/05-database/02-schema-definition.md`](../spec/05-database/02-schema-definition.md) |
| US-017 | Como cliente, quiero ver un enlace de WhatsApp para consultas, para contactar a la tienda sin salir del sitio. | 🟢 | Observación 2 en [`spec/README.md`](../spec/README.md) |

---

## EP-003 — Carrito de Compras

### FT-003.1 — Gestión del Carrito

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-018 | Como cliente, quiero agregar un producto al carrito desde el catálogo o el detalle, para seleccionar lo que deseo comprar. | 🔴 | RFC-04 |
| US-019 | Como cliente, quiero editar la cantidad de cada producto en el carrito, para ajustar mi pedido antes de pagar. | 🔴 | RFC-04 |
| US-020 | Como cliente, quiero eliminar un producto del carrito, para descartar artículos que ya no quiero. | 🔴 | RFC-04 |
| US-021 | Como cliente, quiero que el carrito persista si cierro el navegador, para no perder mi selección. | 🔴 | RFC-04, [`spec/07-frontend/02-javascript-modules.md`](../spec/07-frontend/02-javascript-modules.md) |
| US-022 | Como cliente, quiero ver un resumen del carrito (subtotal, total de ítems), para saber cuánto voy a gastar. | 🟡 | RFC-04 |

---

## EP-004 — Checkout y Captura de Datos del Cliente

### FT-004.1 — Formulario de Checkout

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-023 | Como cliente, quiero ingresar mis datos personales (nombre, cédula/RUC, teléfono, email, dirección) en el checkout, para que la tienda pueda procesar y entregar mi pedido. | 🔴 | RFC-05 |
| US-024 | Como sistema, quiero validar los datos del formulario de checkout en el servidor, para garantizar la integridad de la información del pedido. | 🔴 | [`spec/06-backend/02-controllers.md`](../spec/06-backend/02-controllers.md), RN-07 |

### FT-004.2 — Creación del Pedido

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-025 | Como sistema, quiero generar un número de orden único al procesar el checkout, para identificar el pedido de forma inequívoca. | 🔴 | RFC-06 |
| US-026 | Como sistema, quiero calcular el total del pedido en el servidor usando precios de BD, para evitar manipulación de precios desde el frontend. | 🔴 | RN-07, [`spec/08-security/02-upload-security.md`](../spec/08-security/02-upload-security.md) |
| US-027 | Como sistema, quiero guardar el pedido con estado `pending_payment` y sus ítems como snapshot de precios, para preservar los precios al momento de la compra. | 🔴 | [`spec/05-database/02-schema-definition.md`](../spec/05-database/02-schema-definition.md) |
| US-028 | Como sistema, quiero despachar el evento `OrderCreated` al crear el pedido, para enviar el email de confirmación de recibo al cliente. | 🟡 | [`spec/06-backend/05-events.md`](../spec/06-backend/05-events.md) |

---

## EP-005 — Módulo de Pagos

### FT-005.1 — Selección del Método de Pago

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-029 | Como cliente, quiero elegir entre Tarjeta, Transferencia o Deuna como método de pago, para pagar de la forma que más me convenga. | 🔴 | RFC-07, RN-02 |

### FT-005.2 — Pago con Tarjeta (Pasarela)

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-030 | Como cliente, quiero ser redirigido al portal seguro de la pasarela al elegir tarjeta, para ingresar mis datos bancarios en un entorno certificado. | 🔴 | RFC-08, RN-04 |
| US-031 | Como sistema, quiero reservar el stock temporalmente por 15 minutos al iniciar el pago con tarjeta, para evitar sobreventas durante el proceso. | 🔴 | RN-06, [`spec/06-backend/04-services-helpers.md`](../spec/06-backend/04-services-helpers.md) |
| US-032 | Como sistema, quiero procesar el webhook de la pasarela con validación de firma e idempotencia, para actualizar el estado del pedido de forma segura y confiable. | 🔴 | RN-08, [`spec/03-architecture/04-webhooks.md`](../spec/03-architecture/04-webhooks.md) |
| US-033 | Como sistema, quiero descontar el stock físico definitivamente solo tras confirmar el pago por webhook, para mantener la integridad del inventario. | 🔴 | RN-04, [`spec/06-backend/05-events.md`](../spec/06-backend/05-events.md) |

### FT-005.3 — Pago Manual (Transferencia / Deuna)

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-034 | Como cliente, quiero ver los datos bancarios o el QR de Deuna al elegir ese método, para realizar la transferencia correctamente. | 🔴 | RFC-08, RN-03 |
| US-035 | Como cliente, quiero subir el comprobante de pago (JPG, PNG o PDF, máx. 4MB), para completar mi pedido y que el administrador lo valide. | 🔴 | RFC-08, [`spec/08-security/02-upload-security.md`](../spec/08-security/02-upload-security.md) |
| US-036 | Como sistema, quiero cambiar el estado del pedido a `validating` al recibir el comprobante, para indicar que está en revisión. | 🔴 | RN-03 |

### FT-005.4 — Pantallas de Retorno del Pago

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-037 | Como cliente, quiero ver una pantalla de éxito con el número de pedido al completar el pago por tarjeta, para confirmar que mi compra fue exitosa. | 🔴 | RFC-09 |
| US-038 | Como cliente, quiero ver una pantalla de error con la opción de reintentar si el pago fue rechazado, para no perder mi pedido. | 🔴 | RFC-09 |
| US-039 | Como cliente, quiero ver una pantalla de estado pendiente si el pago está siendo procesado, para saber que debo esperar. | 🟡 | RFC-09 |

### FT-005.5 — Expiración de Reservas y Limpieza

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-040 | Como sistema, quiero cancelar automáticamente pedidos cuya reserva de stock expiró sin completar el pago, para liberar inventario bloqueado. | 🔴 | RN-06, [`spec/06-backend/06-jobs.md`](../spec/06-backend/06-jobs.md) |

---

## EP-006 — Registro Opcional Post-Compra

### FT-006.1 — Creación de Cuenta Post-Compra

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-041 | Como cliente, quiero registrar una contraseña en la pantalla de confirmación para crear mi cuenta, sin necesidad de ingresar nuevamente mis datos. | 🟡 | RFC-10, RN-05 |
| US-042 | Como sistema, quiero vincular el pedido al usuario recién creado, para que el cliente tenga historial de compras en su cuenta. | 🟡 | [`spec/06-backend/02-controllers.md`](../spec/06-backend/02-controllers.md) |

---

## EP-007 — Panel Administrativo: Catálogo y Operaciones

### FT-007.1 — Dashboard

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-043 | Como administrador, quiero ver métricas rápidas en el dashboard (ventas del mes, pedidos en validación, alertas de stock bajo), para tener una vista general del negocio. | 🟡 | RFA-01 |

### FT-007.2 — Gestión de Categorías

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-044 | Como administrador, quiero crear, editar y eliminar categorías de productos, para organizar el catálogo de la tienda. | 🔴 | RFA-04 |

### FT-007.3 — Gestión de Productos

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-045 | Como administrador, quiero listar, buscar, crear, editar y desactivar productos desde el panel, para gestionar el catálogo de la tienda. | 🔴 | RFA-02, RFA-03 |
| US-046 | Como administrador, quiero subir imágenes de los productos al crearlos o editarlos, para mostrarlos correctamente en el catálogo. | 🟡 | RFA-03 |

### FT-007.4 — Gestión de Inventario

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-047 | Como administrador, quiero ver y actualizar el stock físico de cada producto, para mantener el inventario al día. | 🔴 | RFA-05 |
| US-048 | Como administrador, quiero configurar el umbral mínimo de stock por producto, para recibir alertas antes de que se agote. | 🟡 | RFA-05, [`spec/05-database/02-schema-definition.md`](../spec/05-database/02-schema-definition.md) |

### FT-007.5 — Gestión de Promociones

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-049 | Como administrador, quiero crear cupones de descuento con tipo (porcentaje o valor fijo), validez y número máximo de usos, para ofrecer promociones en la tienda. | 🟡 | RFA-09 |

---

## EP-008 — Panel Administrativo: Gestión de Pedidos

### FT-008.1 — Listado y Filtrado de Pedidos

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-050 | Como administrador, quiero ver todos los pedidos paginados y filtrados por estado, para gestionar eficientemente la operación diaria. | 🔴 | RFA-06 |

### FT-008.2 — Detalle y Acciones sobre Pedidos

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-051 | Como administrador, quiero ver el detalle completo de un pedido con el comprobante (si es manual) o los logs de transacción (si es pasarela), para verificar la información del cobro. | 🔴 | RFA-07 |
| US-052 | Como administrador, quiero aprobar un pedido manual después de verificar el comprobante, para cambiar su estado a `approved` y descontar el inventario. | 🔴 | RFA-07, RN-03 |
| US-053 | Como administrador, quiero rechazar un pedido manual indicando el motivo, para que el cliente sepa por qué fue rechazado y pueda corregirlo. | 🔴 | RFA-07 |
| US-054 | Como administrador, quiero descargar el comprobante de pago de un pedido manual de forma segura, para verificarlo sin acceder al sistema de archivos. | 🔴 | [`spec/08-security/02-upload-security.md`](../spec/08-security/02-upload-security.md) |

---

## EP-009 — Panel Administrativo: Configuración y Administración

### FT-009.1 — Configuración de la Tienda

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-055 | Como administrador, quiero editar los datos de la tienda, cuentas bancarias, QR de Deuna y llaves de pasarelas desde el panel, para mantener la configuración actualizada sin editar código. | 🟡 | RFA-12, [`spec/10-deployment/01-env-variables.md`](../spec/10-deployment/01-env-variables.md) |

### FT-009.2 — Gestión de Usuarios y Clientes

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-056 | Como administrador, quiero ver la lista de clientes registrados con sus datos de contacto, para conocer mi base de compradores. | 🟢 | RFA-08 |
| US-057 | Como administrador, quiero gestionar los usuarios administradores del sistema, para agregar o desactivar accesos al panel. | 🟡 | RFA-11 |

### FT-009.3 — Reportes

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-058 | Como administrador, quiero exportar un reporte de ventas a Excel y ver gráficos básicos por categoría, para analizar el desempeño de la tienda. | 🟢 | RFA-10 |

---

## EP-010 — Seguridad, Testing y Despliegue

### FT-010.1 — Verificación de Seguridad

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-059 | Como desarrollador, quiero verificar que todos los controles de seguridad estén implementados (CSRF, XSS, SQL Injection, PCI-DSS, firma de webhooks), para que el sistema sea seguro. | 🔴 | [`spec/08-security/02-upload-security.md`](../spec/08-security/02-upload-security.md) |

### FT-010.2 — Testing

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-060 | Como desarrollador, quiero ejecutar los 5 casos de prueba definidos en el SPEC y documentar los resultados, para validar que el sistema cumple los requisitos. | 🔴 | [`spec/09-testing/02-test-cases.md`](../spec/09-testing/02-test-cases.md) |
| US-061 | Como desarrollador, quiero implementar pruebas unitarias para `PaymentService` y los adaptadores de pago, para garantizar la fiabilidad del módulo más crítico. | 🟡 | [`spec/09-testing/01-test-plan.md`](../spec/09-testing/01-test-plan.md) |

### FT-010.3 — Despliegue Local

| US | User Story | Prioridad | Referencia SPEC |
| :- | :--- | :- | :--- |
| US-062 | Como desarrollador, quiero desplegar el sistema en el entorno local XAMPP siguiendo la guía del SPEC, para la revisión final del cliente. | 🔴 | [`spec/10-deployment/02-deployment-guide.md`](../spec/10-deployment/02-deployment-guide.md) |
