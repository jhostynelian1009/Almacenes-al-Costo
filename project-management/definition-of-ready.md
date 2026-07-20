# Definition of Ready (DoR) — Almacenes al Costo

> **Alineación vigente:** una historia de EP-005 manual no requiere credenciales ni webhook. Las comprobaciones específicas de pasarela aplican únicamente al alcance futuro de ADR-04. EP-004 debe admitir checkout invitado y EP-014 debe partir de EP-002 sin duplicarlo.

## Propósito del Documento
Establecer los criterios que debe cumplir una **User Story o Task** antes de poder ser seleccionada para su desarrollo dentro de un Sprint. Una tarea que no cumple con todos los criterios de este documento **no puede comenzarse**.

> **Principio**: "Ready" no significa perfecta; significa que el equipo tiene suficiente información para comenzar a trabajar sin bloquearse.

---

## Criterios Generales de Preparación

Una User Story está lista para comenzar cuando cumple con **todos** los siguientes criterios:

### 1. ✅ Documentación técnica disponible
La funcionalidad está documentada en el SPEC con suficiente detalle para ser implementada sin ambigüedades. El desarrollador puede encontrar la especificación técnica completa referenciando el documento correspondiente del SPEC.

*   ¿El comportamiento esperado está descrito en `spec/`?
*   ¿Las reglas de negocio relacionadas están en [`spec/01-business/01-business-rules.md`](../spec/01-business/01-business-rules.md)?
*   ¿Los modelos de datos involucrados están en [`spec/05-database/02-schema-definition.md`](../spec/05-database/02-schema-definition.md)?

### 2. ✅ Criterios de aceptación definidos
Cada User Story tiene al menos **dos criterios de aceptación** verificables, escritos en formato "Dado / Cuando / Entonces" o equivalente. Los criterios indican exactamente qué debe pasar para que la historia se considere completada.

### 3. ✅ Prioridad asignada
La User Story tiene una prioridad (🔴 Alta / 🟡 Media / 🟢 Baja) asignada en [`product-backlog.md`](./product-backlog.md). No se trabaja en User Stories sin prioridad definida.

### 4. ✅ Estimación realizada
La User Story ha sido estimada en puntos de historia por el equipo durante la sesión de Sprint Planning. Estimaciones válidas: 1, 2, 3, 5, 8, 13. Si una historia supera los 8 puntos, debe ser dividida.

### 5. ✅ Dependencias identificadas y resueltas
Las dependencias técnicas de la User Story están explícitamente documentadas en [`product-backlog.md`](./product-backlog.md) o en [`sprint-planning.md`](./sprint-planning.md). Antes de iniciar, todas las dependencias bloqueantes deben estar completadas o en estado "Done".

### 6. ✅ Diseño de interfaz disponible (si aplica)
Si la historia incluye una interfaz de usuario, la especificación visual está disponible en [`spec/04-ui-ux/03-screen-specs.md`](../spec/04-ui-ux/03-screen-specs.md) o en [`spec/07-frontend/`](../spec/07-frontend/). El desarrollador no necesita tomar decisiones de diseño por su cuenta.

### 7. ✅ Claridad sobre la Épica y Feature de pertenencia
La User Story está correctamente categorizada bajo su Épica y Feature correspondiente en [`product-backlog.md`](./product-backlog.md) y en GitHub Projects (con los Labels correctos).

### 8. ✅ Sin bloqueos conocidos
No existe ningún impedimento conocido que impida al desarrollador comenzar el trabajo (credenciales faltantes, infraestructura no disponible, espera de decisiones externas, etc.).

---

## Criterios Específicos por Tipo de Tarea

### Para tareas de Backend (Controladores, Servicios, Modelos)
*   Las tablas de BD necesarias están migradas en el entorno de desarrollo.
*   La interfaz o contrato del servicio está documentada en [`spec/06-backend/04-services-helpers.md`](../spec/06-backend/04-services-helpers.md).
*   Las rutas relacionadas están registradas en [`spec/06-backend/01-routes-map.md`](../spec/06-backend/01-routes-map.md).

### Para tareas de Pagos
*   Las credenciales de la pasarela correspondiente están configuradas en `.env` y `settings`.
*   La especificación del adaptador está en [`spec/06-backend/04-services-helpers.md`](../spec/06-backend/04-services-helpers.md).
*   El flujo de webhook está completamente documentado en [`spec/03-architecture/04-webhooks.md`](../spec/03-architecture/04-webhooks.md).

### Para tareas de Seguridad
*   El control de seguridad a implementar está detallado en [`spec/08-security/02-upload-security.md`](../spec/08-security/02-upload-security.md).
*   El caso de prueba de verificación está definido en [`spec/09-testing/02-test-cases.md`](../spec/09-testing/02-test-cases.md).

### Para tareas de Testing
*   El caso de prueba está completamente definido en [`spec/09-testing/02-test-cases.md`](../spec/09-testing/02-test-cases.md).
*   El entorno de testing está configurado y los datos de prueba están disponibles.

---

## Checklist Rápido (para usar en Sprint Planning)

```
[ ] ¿El SPEC tiene documentación suficiente para esta tarea?
[ ] ¿Tiene al menos 2 criterios de aceptación verificables?
[ ] ¿Tiene prioridad asignada?
[ ] ¿Fue estimada en puntos de historia?
[ ] ¿Sus dependencias están completas?
[ ] ¿El diseño de UI está disponible (si aplica)?
[ ] ¿No tiene bloqueos conocidos?
```

> Si alguna casilla no está marcada, la historia **no puede entrar al Sprint**.
