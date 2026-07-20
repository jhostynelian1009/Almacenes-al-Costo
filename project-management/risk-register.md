# Registro de Riesgos — Almacenes al Costo

## Propósito del Documento
Identificar, evaluar y documentar los riesgos conocidos del proyecto, junto con su probabilidad de ocurrencia, impacto en el proyecto y el plan de mitigación correspondiente. Este registro se actualiza al inicio de cada Sprint.

> **Escala de Probabilidad**: Alta (>70%) / Media (30-70%) / Baja (<30%)
> **Escala de Impacto**: Alto (bloquea entrega) / Medio (retraso significativo) / Bajo (retraso menor)
> **Nivel de riesgo** = Probabilidad × Impacto

---

## Catálogo de Riesgos

### R-001 — Integración con Pasarelas de Pago Más Compleja de lo Estimado

| Campo | Detalle |
| :--- | :--- |
| **ID** | R-001 |
| **Descripción** | La documentación de la API de una o más pasarelas de pago (Stripe, PayPhone, Datafast, Kushki) puede presentar comportamientos no documentados, cambios de versión o requerir procesos de aprobación comercial (onboarding, contratos, cuenta activa) que retrasen la integración. |
| **Probabilidad** | Media |
| **Impacto** | Alto |
| **Nivel** | 🔴 Alto |
| **Sprint afectado** | Sprint 3 |
| **Plan de mitigación** | 1. Iniciar el proceso de alta/onboarding con las pasarelas antes de comenzar el Sprint 3. 2. La arquitectura Strategy/Adapter (ver [`spec/03-architecture/01-system-architecture.md`](../spec/03-architecture/01-system-architecture.md)) permite implementar primero el `ManualPaymentAdapter` para validar el flujo completo. 3. Priorizar la integración con una sola pasarela como prueba de concepto antes de las demás. 4. Usar cuentas de prueba (sandbox) para desarrollo. |
| **Estado** | ⚠️ Activo — Monitorear al inicio de Sprint 3 |

---

### R-002 — Disponibilidad de Credenciales de Pasarelas en Tiempo de Desarrollo

| Campo | Detalle |
| :--- | :--- |
| **ID** | R-002 |
| **Descripción** | El cliente puede no tener disponibles a tiempo las credenciales API de las pasarelas de pago (llaves de producción o sandbox), bloqueando el desarrollo y las pruebas del Sprint 3. |
| **Probabilidad** | Alta |
| **Impacto** | Medio |
| **Nivel** | 🟡 Medio |
| **Sprint afectado** | Sprint 3 |
| **Plan de mitigación** | 1. Solicitar al cliente las credenciales con al menos 2 semanas de anticipación al inicio del Sprint 3. 2. Usar cuentas de sandbox propias para el desarrollo inicial. 3. El flujo manual (Transferencia/Deuna) no requiere credenciales de pasarela y puede desarrollarse en paralelo. |
| **Estado** | ⚠️ Activo — Gestionar con el cliente en Sprint 1 |

---

### R-003 — Retrasos por Webhook Testing en Entorno Local

| Campo | Detalle |
| :--- | :--- |
| **ID** | R-003 |
| **Descripción** | Los webhooks de pasarelas de pago requieren una URL pública para ser enviados. En un entorno local (XAMPP), no es posible recibir webhooks directamente sin configuración adicional, lo que puede complicar las pruebas. |
| **Probabilidad** | Alta |
| **Impacto** | Medio |
| **Nivel** | 🟡 Medio |
| **Sprint afectado** | Sprint 3 |
| **Plan de mitigación** | 1. Usar herramientas de túnel como `ngrok` o `Stripe CLI` para exponer el entorno local durante el desarrollo. 2. Documentar el proceso de configuración del túnel como parte de la guía de desarrollo. 3. Implementar tests unitarios que simulen la llegada de webhooks sin requerir conexión real a la pasarela. |
| **Estado** | ⚠️ Activo — Preparar solución antes de Sprint 3 |

---

### R-004 — Alcance Expandido por Solicitudes del Cliente Durante el Desarrollo

| Campo | Detalle |
| :--- | :--- |
| **ID** | R-004 |
| **Descripción** | El cliente puede solicitar nuevas funcionalidades o cambios de alcance durante el desarrollo del MVP, generando deuda de planificación y potenciales retrasos. |
| **Probabilidad** | Media |
| **Impacto** | Medio |
| **Nivel** | 🟡 Medio |
| **Sprint afectado** | Cualquiera |
| **Plan de mitigación** | 1. El SPEC está congelado y sirve como referencia contractual del alcance acordado. 2. Cualquier solicitud fuera del SPEC se registra en el backlog como candidata para V1.1 o V2.0 (ver [`release-plan.md`](./release-plan.md)). 3. Se comunica al cliente el impacto en tiempo y costo de cualquier cambio de alcance antes de aceptarlo. |
| **Estado** | ✅ Mitigado — SPEC congelado |

---

### R-005 — Complejidad del Módulo de Inventario con Reserva Temporal

| Campo | Detalle |
| :--- | :--- |
| **ID** | R-005 |
| **Descripción** | El mecanismo de reserva temporal de stock (`reserved_stock`) con expiración automática en 15 minutos (RN-06) y el Job `ExpirePaymentReservations` pueden presentar condiciones de carrera (race conditions) si múltiples clientes compran el mismo producto simultáneamente. |
| **Probabilidad** | Baja |
| **Impacto** | Alto |
| **Nivel** | 🟡 Medio |
| **Sprint afectado** | Sprint 3 |
| **Plan de mitigación** | 1. Usar transacciones de base de datos (`DB::transaction`) al decrementar `stock` y `reserved_stock` para garantizar atomicidad. 2. Validar que `stock - reserved_stock >= quantity` antes de crear la reserva. 3. Documentar el comportamiento esperado en [`spec/01-business/01-business-rules.md`](../spec/01-business/01-business-rules.md) RN-06. |
| **Estado** | ✅ Mitigado por diseño — Verificar implementación en Sprint 3 |

---

### R-006 — Configuración Incorrecta del Exclusión CSRF del Webhook

| Campo | Detalle |
| :--- | :--- |
| **ID** | R-006 |
| **Descripción** | Si el endpoint de webhooks no es correctamente excluido de la verificación CSRF, las pasarelas recibirán respuestas HTTP 419 (CSRF Token Mismatch) y los pagos no podrán confirmarse, bloqueando el flujo de pasarela. |
| **Probabilidad** | Media |
| **Impacto** | Alto |
| **Nivel** | 🔴 Alto |
| **Sprint afectado** | Sprint 3 |
| **Plan de mitigación** | 1. La exclusión CSRF está documentada explícitamente en [`spec/08-security/02-upload-security.md`](../spec/08-security/02-upload-security.md) Sección 4. 2. Incluir la verificación de la exclusión CSRF como criterio de aceptación del US-032. 3. Verificar en el Sprint Review del Sprint 3. |
| **Estado** | ⚠️ Activo — Validar en Sprint 3 |

---

### R-007 — Diseño Visual No Alineado con la Paleta del SPEC

| Campo | Detalle |
| :--- | :--- |
| **ID** | R-007 |
| **Descripción** | El desarrollador puede implementar estilos CSS que no correspondan con la paleta cálida (Crema, Naranja, Durazno, Café) definida en el SPEC, generando retrabajo en las revisiones del cliente. |
| **Probabilidad** | Media |
| **Impacto** | Bajo |
| **Nivel** | 🟢 Bajo |
| **Sprint afectado** | Sprint 2, 3, 4 |
| **Plan de mitigación** | 1. Implementar las variables CSS de la paleta en Sprint 1 (US-008 y US-009) antes de cualquier vista. 2. Referenciar [`spec/07-frontend/03-bootstrap-custom.md`](../spec/07-frontend/03-bootstrap-custom.md) al desarrollar cada vista. 3. Incluir la revisión de diseño como parte de la Definition of Done. |
| **Estado** | ⚠️ Activo — Mitigar desde Sprint 1 |

---

### R-008 — Limitaciones del Entorno XAMPP para Colas y Jobs

| Campo | Detalle |
| :--- | :--- |
| **ID** | R-008 |
| **Descripción** | El entorno local XAMPP no ejecuta automáticamente el worker de colas de Laravel (`queue:work`). Los Jobs asíncronos (envío de emails, actualización de inventario) no se procesarán si el worker no está corriendo manualmente. |
| **Probabilidad** | Alta |
| **Impacto** | Bajo |
| **Nivel** | 🟢 Bajo |
| **Sprint afectado** | Sprint 3, 5 |
| **Plan de mitigación** | 1. Documentar en la guía de desarrollo el comando necesario: `php artisan queue:work --queue=critical,default,emails`. 2. Para el entorno de desarrollo, considerar `QUEUE_CONNECTION=sync` para simplificar el flujo de pruebas (ejecuta jobs de forma inmediata y sincrónica). 3. Cambiar a `QUEUE_CONNECTION=database` para el entorno de staging/revisión del cliente. |
| **Estado** | ✅ Mitigado — Documentar en Sprint 1 |

---

## Resumen del Registro

| ID | Descripción resumida | Probabilidad | Impacto | Nivel | Estado |
| :- | :--- | :- | :- | :- | :- |
| R-001 | API de pasarelas más compleja de lo estimado | Media | Alto | 🔴 Alto | ⚠️ Activo |
| R-002 | Credenciales de pasarelas no disponibles a tiempo | Alta | Medio | 🟡 Medio | ⚠️ Activo |
| R-003 | Webhooks no testables en entorno local sin túnel | Alta | Medio | 🟡 Medio | ⚠️ Activo |
| R-004 | Cambios de alcance solicitados por el cliente | Media | Medio | 🟡 Medio | ✅ Mitigado |
| R-005 | Race conditions en reserva temporal de stock | Baja | Alto | 🟡 Medio | ✅ Mitigado |
| R-006 | Exclusión CSRF del webhook mal configurada | Media | Alto | 🔴 Alto | ⚠️ Activo |
| R-007 | Diseño visual no alineado con paleta del SPEC | Media | Bajo | 🟢 Bajo | ⚠️ Activo |
| R-008 | Colas no procesadas automáticamente en XAMPP | Alta | Bajo | 🟢 Bajo | ✅ Mitigado |
