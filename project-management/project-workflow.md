# Flujo Oficial de Trabajo — Almacenes al Costo

## Propósito del Documento
Documentar el flujo de trabajo oficial que sigue cada elemento de trabajo (Issue) desde su origen en el SPEC hasta su finalización y cierre. Este flujo es el acuerdo de proceso del equipo y debe seguirse consistentemente para mantener trazabilidad y calidad.

---

## Flujo General del Proyecto

```
┌─────────────────────────────────────────────────────────────────────┐
│                          SPEC (spec/)                               │
│          Única fuente de verdad del sistema a construir             │
│  Requisitos · Arquitectura · BD · Seguridad · Flujos · Diseño      │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│               PRODUCT BACKLOG (product-backlog.md)                  │
│       Estructura: EPIC → FEATURE → USER STORY → TASK               │
│         Todo el trabajo identificado, priorizado y trazado          │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│               SPRINT PLANNING (sprint-planning.md)                  │
│     Selección de US para el Sprint · Estimación en puntos          │
│     Verificación de Definition of Ready (definition-of-ready.md)   │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     GITHUB ISSUES                                   │
│    Conversión de cada User Story en un Issue de GitHub             │
│    Labels · Milestone · Épica vinculada · Estimación               │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    GITHUB PROJECTS (Tablero)                        │
│              Seguimiento visual del progreso del Sprint             │
│         Columnas: Backlog → Specs → UI/UX → Dev → Testing          │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│                       DEVELOPMENT                                   │
│   Implementación del código según spec/ · Rama de feature          │
│      Commits vinculados al Issue (`#N` en el mensaje)              │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│                         TESTING                                     │
│     Verificación de criterios de aceptación · Pruebas manuales    │
│       Pruebas automatizadas · Checklists de seguridad              │
│     Verificación de Definition of Done (definition-of-done.md)    │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      CLIENT REVIEW                                  │
│     Demostración al cliente del entregable del Sprint              │
│      Feedback registrado · Ajustes aprobados o diferidos           │
│           Sprint Review formal al final de cada Sprint             │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────┐
│                          DONE ✅                                     │
│          Issue cerrado · Columna "Finalizado" en el tablero        │
│        Trabajo contabilizado para la velocidad del equipo          │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Descripción Detallada de Cada Etapa

### 1. SPEC
**Propósito**: La especificación técnica y funcional del sistema. Es la única fuente de verdad sobre **qué** se construirá.

**Quién lo usa**: Todos los miembros del equipo. El desarrollador lo consulta antes de comenzar cualquier tarea.

**Regla**: El SPEC es la fuente de verdad. Si hay duda sobre el comportamiento correcto de una función, la respuesta está en `spec/`. Si no está en el SPEC, es una nueva funcionalidad que debe pasar por aprobación antes de implementarse.

**Localización**: [`spec/`](../spec/README.md)

---

### 2. Product Backlog
**Propósito**: Inventario completo y priorizado de todo el trabajo del proyecto, organizado en la jerarquía Épica → Feature → User Story → Task.

**Quién lo usa**: Product Owner y equipo en Sprint Planning.

**Regla**: Nada que no esté en el SPEC puede añadirse al backlog sin aprobación explícita. El backlog es la traducción del SPEC a unidades de trabajo ejecutables.

**Localización**: [`product-backlog.md`](./product-backlog.md)

---

### 3. Sprint Planning
**Propósito**: Ceremonia de planificación donde el equipo selecciona las User Stories del backlog para el Sprint, verifica que cumplen la Definition of Ready y las estima en puntos de historia.

**Frecuencia**: Al inicio de cada Sprint (cada 2 semanas).

**Salida**: Lista de Issues a crear en GitHub + estimaciones asignadas.

**Documentos de referencia**: [`sprint-planning.md`](./sprint-planning.md), [`definition-of-ready.md`](./definition-of-ready.md)

---

### 4. GitHub Issues
**Propósito**: Representación ejecutable de cada User Story dentro de GitHub. Cada Issue es la unidad de trabajo que el desarrollador toma, desarrolla y cierra.

**Contenido esperado de cada Issue**:
*   Título: `[EP-00X] [US-0XX] Descripción breve`
*   Descripción: User Story completa + criterios de aceptación
*   Labels: Épica + Tipo + Prioridad
*   Milestone: Sprint correspondiente
*   Estimación: Puntos de historia

**Referencia**: [`github-project-structure.md`](./github-project-structure.md)

---

### 5. GitHub Projects (Tablero)
**Propósito**: Herramienta de seguimiento visual del progreso del Sprint. Permite ver el estado de todos los Issues del Sprint en tiempo real.

**Regla**: El tablero refleja la realidad del trabajo. Cada desarrollador es responsable de mover sus Issues entre columnas.

**Referencia**: [`github-project-structure.md`](./github-project-structure.md)

---

### 6. Development
**Propósito**: Implementación del código según las especificaciones del SPEC y los criterios de aceptación del Issue.

**Convenciones**:
*   Trabajar en una rama separada por Issue: `feature/US-XXX-descripcion`.
*   Referenciar el Issue en cada commit: `feat: agregar validación de firma webhook (#32)`.
*   Consultar el SPEC si surge alguna duda sobre el comportamiento esperado.
*   No agregar funcionalidades fuera del alcance del Issue.

---

### 7. Testing
**Propósito**: Verificar que la implementación cumple todos los criterios de aceptación y la Definition of Done antes de presentarla al cliente.

**Tipos de pruebas aplicables**:
*   Pruebas manuales de flujo (checklist del criterio de aceptación).
*   Pruebas unitarias para lógica de negocio crítica (módulo de pagos).
*   Verificación de controles de seguridad.

**Documentos de referencia**: [`definition-of-done.md`](./definition-of-done.md), [`spec/09-testing/02-test-cases.md`](../spec/09-testing/02-test-cases.md)

---

### 8. Client Review
**Propósito**: Demostración al cliente de los entregables del Sprint para obtener feedback y aprobación.

**Formato**: Sprint Review al final de cada Sprint (demo en vivo del entorno local).

**Salida**: Lista de ajustes aprobados (si hay) y confirmación de continuidad al siguiente Sprint.

---

### 9. Done ✅
**Propósito**: Estado final de un Issue cuando cumple la Definition of Done y fue aprobado por el cliente.

**Acciones**:
*   Mover el Issue a la columna "Finalizado" en GitHub Projects.
*   Cerrar el Issue con el comentario de cierre correspondiente.
*   Contabilizar los puntos de historia para la velocidad del equipo.

---

## Flujo de Manejo de Defectos (Bugs)

```
Bug detectado → Issue tipo "Bug" creado → Priorizado en el backlog
    → Si bloquea el Sprint actual: se atiende de inmediato
    → Si no bloquea: se agenda en el siguiente Sprint Planning
```

*   Los bugs detectados durante Testing del mismo Sprint se resuelven dentro del Sprint antes de cerrar el Issue.
*   Los bugs reportados por el cliente en Client Review se registran como nuevos Issues con label `bug` y se priorizan en el siguiente Sprint.
