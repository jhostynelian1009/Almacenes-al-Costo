# Estructura de GitHub Projects — Almacenes al Costo

> **Vigencia:** Se conservan jerarquía, labels, milestones, convenciones y flujo. La tabla histórica de labels EP-001–EP-010 debe reemplazarse en GitHub por EP-001–EP-015 con los nombres vigentes de `epics.md`; los ejemplos de webhook se consideran alcance futuro.

## Columnas oficiales vigentes

`Backlog → Spec → UI/UX → Desarrollo → Testing → Cliente → Finalizado`

La numeración de épicas es un identificador histórico, no el orden de implementación. Las dependencias se documentan en `epics.md` y los Issues de pasarela automática no pertenecen al milestone MVP.

## Propósito del Documento
Documentar la estructura, convenciones y configuración utilizada en GitHub para la gestión y seguimiento del proyecto. Este documento es la guía de referencia para crear Issues, configurar el tablero y mantener la trazabilidad del trabajo.

> **Principio**: GitHub Projects es la herramienta de **ejecución y seguimiento**. La planificación vive en `project-management/`. El código vive en el repositorio. El SPEC vive en `spec/`.

---

## 1. Jerarquía de Issues

El trabajo del proyecto se organiza en la siguiente jerarquía dentro de GitHub:

```
Epic Issue (EP-001)
  └── Feature Issue (FT-001.1)
        └── User Story Issue (US-001)
              └── Sub-Issue o Task (T-001-a)
```

| Nivel | Qué representa | Quién lo crea |
| :--- | :--- | :--- |
| **Epic** | Una capacidad mayor del sistema (ej. "Módulo de Pagos") | Project Manager / Scrum Master |
| **Feature** | Un grupo de funcionalidades relacionadas dentro de la Épica | Project Manager |
| **User Story** | Una funcionalidad específica desde la perspectiva del usuario | Product Owner |
| **Task / Sub-Issue** | Una tarea técnica concreta derivada de una User Story | Desarrollador |

---

## 2. Labels (Etiquetas)

Los labels permiten filtrar y clasificar los Issues en GitHub. Se configuran una sola vez en el repositorio y se aplican consistentemente.

### Labels de Tipo de Issue

| Label | Color | Propósito |
| :--- | :--- | :--- |
| `epic` | 🟣 Morado | Identifica un Issue de nivel Épica |
| `feature` | 🔵 Azul oscuro | Identifica un Issue de nivel Feature |
| `user-story` | 🔵 Azul | Identifica una User Story |
| `task` | ⚫ Gris | Identifica una tarea técnica derivada |
| `bug` | 🔴 Rojo | Defecto detectado en el sistema |
| `chore` | ⚪ Blanco | Tarea de mantenimiento (dependencias, limpieza) |
| `documentation` | 🟤 Marrón | Actualización de documentación |

### Labels de Épica (para trazabilidad)

| Label | Épica correspondiente |
| :--- | :--- |
| `EP-001` | Infraestructura y Configuración Base |
| `EP-002` | Catálogo Público de Productos |
| `EP-003` | Carrito de Compras |
| `EP-004` | Checkout y Captura de Datos |
| `EP-005` | Módulo de Pagos |
| `EP-006` | Registro Post-Compra |
| `EP-007` | Panel Admin: Catálogo y Operaciones |
| `EP-008` | Panel Admin: Gestión de Pedidos |
| `EP-009` | Panel Admin: Configuración |
| `EP-010` | Seguridad, Testing y Despliegue |

### Labels de Prioridad

| Label | Significado |
| :--- | :--- |
| `priority: high` | 🔴 Alta — Bloquea o es crítico para el MVP |
| `priority: medium` | 🟡 Media — Importante pero no bloqueante |
| `priority: low` | 🟢 Baja — Deseable, puede diferirse a V1.1 |

### Labels de Área Técnica

| Label | Área |
| :--- | :--- |
| `area: backend` | Lógica de servidor, controladores, servicios |
| `area: frontend` | Vistas Blade, CSS, JavaScript |
| `area: database` | Migraciones, modelos, seeds |
| `area: payments` | Módulo de pasarelas y pagos |
| `area: security` | Controles de seguridad, CSRF, XSS |
| `area: testing` | Pruebas, QA |
| `area: devops` | Configuración, despliegue |

---

## 3. Milestones (Hitos)

Los Milestones representan los Sprints y permiten filtrar Issues por Sprint activo.

| Milestone | Sprint | Fecha tentativa |
| :--- | :--- | :--- |
| `M1: Infraestructura Base` | Sprint 1 | Semana 1-2 |
| `M2: Sitio Público` | Sprint 2 | Semana 3-4 |
| `M3: Flujo de Compra y Pagos` | Sprint 3 | Semana 5-6 |
| `M4: Panel Administrativo` | Sprint 4 | Semana 7-8 |
| `M5: QA y Entrega MVP` | Sprint 5 | Semana 9-10 |

---

## 4. Estructura del Tablero (GitHub Projects — Vista Kanban)

El tablero de GitHub Projects tendrá las siguientes columnas en orden:

### Columna: `📋 Backlog`
**Propósito**: Issues identificados y priorizados que aún no han sido asignados a un Sprint activo.

*   Contiene todas las User Stories y Tasks pendientes.
*   Se ordena por prioridad (Alta arriba, Baja abajo).
*   Los Issues aquí deben cumplir la **Definition of Ready** antes de moverse al Sprint.

---

### Columna: `📖 Especificaciones`
**Propósito**: Issues del Sprint actual que el desarrollador está revisando en el SPEC antes de comenzar a codificar.

*   El desarrollador mueve el Issue aquí cuando está leyendo la documentación técnica del SPEC.
*   Útil para identificar si hay dudas o ambigüedades antes de comenzar.
*   Tiempo esperado en esta columna: máximo 1 día.

---

### Columna: `🎨 UI/UX`
**Propósito**: Issues del Sprint actual que requieren decisiones o trabajo de interfaz antes de la implementación.

*   Se usa cuando hay que revisar `spec/04-ui-ux/` o preparar componentes visuales antes del desarrollo.
*   Aplica principalmente en Sprints 2, 3 y 4.
*   Issues sin componente de UI saltan directamente a Desarrollo.

---

### Columna: `💻 Desarrollo`
**Propósito**: Issues en proceso de implementación activa.

*   Un desarrollador tiene máximo **2 Issues** en esta columna simultáneamente.
*   El Issue permanece aquí desde el primer commit hasta que la funcionalidad está implementada.
*   Al terminar la implementación, se mueve a Testing.

---

### Columna: `🧪 Testing`
**Propósito**: Issues implementados que están siendo verificados contra los criterios de aceptación y la Definition of Done.

*   El desarrollador (o un reviewer) verifica la funcionalidad contra el checklist de [`definition-of-done.md`](./definition-of-done.md).
*   Si se encuentra un bug: se crea un Issue de tipo `bug` vinculado al Issue original y se regresa el Issue a Desarrollo.
*   Si pasa todas las verificaciones: se mueve a Cliente.

---

### Columna: `👤 Cliente`
**Propósito**: Issues listos para ser presentados al cliente en la Sprint Review.

*   Aquí se acumulan los Issues terminados hasta el Sprint Review formal.
*   El cliente puede observar qué entregables están listos para su revisión.
*   Tras la aprobación del cliente: se mueve a Finalizado.

---

### Columna: `✅ Finalizado`
**Propósito**: Issues completamente terminados, aprobados por el cliente y cerrados.

*   El Issue está cerrado en GitHub con el comentario de cierre.
*   Los puntos de historia se contabilizan para la velocidad del Sprint.
*   Nada vuelve desde esta columna (si hay un problema post-entrega, se crea un nuevo Issue de tipo `bug`).

---

## 5. Convenciones de Nomenclatura

### Título de Issues

```
[TIPO][CÓDIGO] Descripción breve en imperativo
```

**Ejemplos**:
*   `[EPIC][EP-005] Módulo de Pagos`
*   `[US][US-032] Procesar webhook de pasarela con validación de firma e idempotencia`
*   `[TASK][T-032-a] Implementar método validateSignature en StripeAdapter`
*   `[BUG] Webhook devuelve 419 CSRF al recibir notificación de Stripe`

### Rama de Git

```
feature/US-XXX-descripcion-corta
fix/BUG-XXX-descripcion-corta
chore/descripcion-corta
```

### Mensaje de Commit

```
tipo(scope): descripción corta (#numero-issue)
```

**Ejemplos**:
*   `feat(payments): agregar validación de firma HMAC para Stripe (#32)`
*   `fix(webhook): excluir ruta webhook de verificación CSRF (#33)`
*   `test(payments): agregar pruebas unitarias para PaymentService (#61)`

---

## 6. Flujo de Creación de un Issue

Al convertir una User Story del backlog en un Issue de GitHub:

1.  **Título**: Seguir la convención de nomenclatura.
2.  **Descripción**: Incluir:
    *   User Story: "Como [rol], quiero [acción] para [beneficio]"
    *   Criterios de aceptación (mínimo 2, formato: "✅ Dado/Cuando/Entonces")
    *   Referencia al SPEC (link al documento correspondiente)
    *   Estimación en puntos de historia
3.  **Labels**: Aplicar tipo + épica + prioridad + área técnica.
4.  **Milestone**: Asignar al Sprint correspondiente.
5.  **Épica vinculada**: Si GitHub soporta Sub-Issues, vincular al Issue de la épica padre.

---

## 7. Referencias

*   [product-backlog.md](./product-backlog.md) — Fuente de las User Stories
*   [epics.md](./epics.md) — Definición de las Épicas
*   [sprint-planning.md](./sprint-planning.md) — Qué Issues van a cada Sprint
*   [project-workflow.md](./project-workflow.md) — Flujo completo del trabajo
*   [definition-of-ready.md](./definition-of-ready.md) — Cuándo un Issue puede entrar al Sprint
*   [definition-of-done.md](./definition-of-done.md) — Cuándo un Issue puede cerrarse
