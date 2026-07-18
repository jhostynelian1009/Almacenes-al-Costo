# Gestión del Proyecto — Almacenes al Costo

## Propósito de esta Carpeta

Este directorio contiene toda la documentación de **planificación y gestión del proyecto** de desarrollo del sistema *Almacenes al Costo*. Es un directorio completamente independiente del directorio `spec/`.

---

## Separación de Responsabilidades

| Directorio | Responde a | Audiencia principal |
| :--- | :--- | :--- |
| `spec/` | **QUÉ** sistema se construirá: requisitos, arquitectura, base de datos, seguridad, flujos de negocio. | Arquitecto, desarrollador, agente de IA (Codex) |
| `project-management/` | **CÓMO** se desarrollará el proyecto: planificación, sprints, backlog, riesgos, flujo de trabajo. | Project Manager, equipo de desarrollo, cliente |

> **Regla fundamental**: `spec/` es la única fuente de verdad técnica. Cuando un documento de esta carpeta necesite referenciar un requisito, arquitectura o módulo, **siempre enlazará al documento correspondiente de `spec/`** en lugar de duplicar su contenido.

---

## Estructura del Directorio

```
project-management/
├── README.md                    # Este documento (propósito y navegación)
├── epics.md                     # Épicas identificadas del proyecto
├── product-backlog.md           # Product Backlog organizado (Epic → Feature → US → Task)
├── release-plan.md              # Plan de versiones (V1.0, V1.1, V2.0)
├── sprint-planning.md           # Planificación detallada de los 5 sprints
├── definition-of-ready.md      # Criterios para que una tarea pueda comenzar
├── definition-of-done.md       # Criterios para que una tarea se considere terminada
├── risk-register.md             # Registro de riesgos identificados
├── project-workflow.md          # Flujo oficial de trabajo del proyecto
└── github-project-structure.md # Estructura y convenciones en GitHub Projects
```

---

## Herramienta de Ejecución: GitHub Projects

**GitHub Projects** es la herramienta de ejecución y seguimiento del proyecto. Su función es exclusivamente operativa:

*   Convertir el Product Backlog en Issues.
*   Organizar el trabajo en el tablero Kanban.
*   Realizar el seguimiento del progreso de cada Sprint.
*   Registrar el historial de trabajo del equipo.

> **GitHub Projects no reemplaza esta documentación.** La planificación vive aquí. GitHub Projects es la herramienta de ejecución, no el origen de la verdad. La estructura detallada de GitHub se documenta en [`github-project-structure.md`](./github-project-structure.md).

---

## Flujo de Información

```
spec/                   →   project-management/        →   GitHub Projects
(Qué construir)             (Cómo desarrollarlo)           (Seguimiento diario)
Requisitos funcionales  →   Product Backlog            →   Issues + Tablero
Arquitectura            →   Épicas + Sprints           →   Milestones
Reglas de negocio       →   User Stories               →   Labels + Sub-issues
```

---

## Referencias al SPEC

*   [spec/README.md](../spec/README.md) — Mapa de navegación de la especificación técnica
*   [spec/12-roadmap/01-mvp-release.md](../spec/12-roadmap/01-mvp-release.md) — Hitos del MVP
*   [spec/02-requirements/01-functional-requirements.md](../spec/02-requirements/01-functional-requirements.md) — Requisitos funcionales
*   [spec/01-business/01-business-rules.md](../spec/01-business/01-business-rules.md) — Reglas de negocio
