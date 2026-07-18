# Definition of Done (DoD) — Almacenes al Costo

## Propósito del Documento
Establecer los criterios que debe cumplir una **User Story o Task** para ser declarada **"Done" (Terminada)** y poder cerrar el Issue correspondiente en GitHub Projects. Una tarea no está "Done" hasta que cumple con **todos** los criterios aplicables de este documento.

> **Principio**: "Done" significa terminado, probado, seguro y listo para ser revisado por el cliente. No significa "el código funciona en mi máquina".

---

## Criterios Generales de Completitud

### 1. ✅ Código implementado
*   La funcionalidad está completamente implementada según la especificación del SPEC.
*   El código sigue la estructura de directorios definida en [`spec/03-architecture/03-directory-structure.md`](../spec/03-architecture/03-directory-structure.md).
*   No quedan comentarios `TODO`, `FIXME` o `HACK` pendientes en el código entregado.
*   Los nombres de clases, métodos y variables son descriptivos y siguen las convenciones de Laravel.

### 2. ✅ Cumple el SPEC
*   La implementación respeta todas las reglas de negocio documentadas en [`spec/01-business/01-business-rules.md`](../spec/01-business/01-business-rules.md).
*   Los estados de `orders.status` y `payments.status` usados en el código son exactamente los definidos en el SPEC (fuente canónica).
*   Las relaciones entre modelos Eloquent coinciden con el esquema de [`spec/05-database/02-schema-definition.md`](../spec/05-database/02-schema-definition.md).

### 3. ✅ Pruebas realizadas
*   Se ejecutaron las pruebas unitarias o de integración pertinentes y todas pasan sin errores.
*   Si la tarea involucra el módulo de pagos, se ejecutó la prueba manual del flujo completo en entorno local.
*   Si la tarea involucra webhooks, se verificó la validación de firma y la idempotencia.
*   Los casos de prueba relacionados de [`spec/09-testing/02-test-cases.md`](../spec/09-testing/02-test-cases.md) pasan exitosamente.

### 4. ✅ Sin errores conocidos
*   No existe ningún error (bug) conocido y no resuelto en la funcionalidad entregada.
*   No hay excepciones no manejadas que puedan llegar al usuario en producción.
*   Los logs de Laravel no muestran errores ni warnings relacionados con la tarea.

### 5. ✅ Criterios de aceptación verificados
*   Cada criterio de aceptación de la User Story fue verificado manualmente o mediante prueba automatizada.
*   El resultado de la verificación está registrado (comentario en el Issue o en el documento de resultados de testing).

### 6. ✅ Controles de seguridad aplicados
*   Si la tarea involucra formularios: se incluyó `@csrf` en la vista Blade.
*   Si la tarea muestra datos de usuario: se usa `{{ $variable }}` (no `{!! $variable !!}`).
*   Si la tarea involucra subida de archivos: se usa `ReceiptUploadService` con validación MIME.
*   Si la tarea involucra webhooks: el endpoint está excluido de CSRF y valida firma HMAC.
*   Si la tarea involucra consultas a BD: se usa Eloquent ORM (sin SQL crudo con concatenación).

### 7. ✅ Revisión de código completada
*   El código fue revisado por al menos otro miembro del equipo (o autoreviado contra el SPEC si el equipo es individual).
*   No existen comentarios de revisión pendientes de resolver.

### 8. ✅ Issue actualizado en GitHub
*   El Issue en GitHub Projects fue movido a la columna correcta (Testing → Cliente / Finalizado).
*   El Issue tiene los Labels correctos asignados.
*   El commit o Pull Request está vinculado al Issue (`Closes #N`).

---

## Criterios Específicos por Tipo de Tarea

### Para tareas de Base de Datos
*   La migración existe y es reversible (`up` y `down` implementados).
*   Los índices documentados en el SPEC están presentes en la migración.
*   La migración fue ejecutada exitosamente en el entorno local.

### Para tareas de Backend (Controllers, Services, Models)
*   El controlador no contiene lógica de negocio: delega en servicios.
*   El servicio no modifica la respuesta HTTP: solo retorna datos.
*   Las relaciones Eloquent están definidas en ambos lados (bidireccional donde aplica).

### Para tareas del Módulo de Pagos
*   El adaptador implementa los 4 métodos de `PaymentGatewayInterface`.
*   El `ManualPaymentAdapter` no hace llamadas a APIs externas.
*   El `PaymentResponse` se retorna correctamente en todos los casos (éxito, fallo, error).
*   Los montos se calculan en servidor, nunca desde el frontend.

### Para tareas de Frontend (Blade, JavaScript)
*   La vista es responsiva y se ve correctamente en desktop y mobile.
*   Se usan las clases CSS de la paleta definida en [`spec/07-frontend/03-bootstrap-custom.md`](../spec/07-frontend/03-bootstrap-custom.md).
*   `checkout.js` previene el doble envío del formulario.

### Para tareas de Jobs y Events
*   El Job está registrado en la cola correcta (`critical`, `default` o `emails`).
*   El Event está registrado en `EventServiceProvider`.
*   El Job maneja fallos con reintentos según lo documentado en [`spec/06-backend/06-jobs.md`](../spec/06-backend/06-jobs.md).

---

## Checklist Rápido (para cerrar un Issue)

```
[ ] ¿El código está completamente implementado según el SPEC?
[ ] ¿Se ejecutaron y pasaron todas las pruebas pertinentes?
[ ] ¿No hay errores conocidos?
[ ] ¿Los criterios de aceptación fueron verificados uno por uno?
[ ] ¿Los controles de seguridad aplicables están implementados?
[ ] ¿El código fue revisado?
[ ] ¿El Issue en GitHub Projects fue actualizado al estado correcto?
[ ] ¿El commit está vinculado al Issue?
```

> Si alguna casilla no está marcada, la tarea **no puede declararse "Done"**.
