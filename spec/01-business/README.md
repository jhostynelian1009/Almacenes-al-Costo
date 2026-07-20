# Sección 01: Modelo de Negocio y Procesos - Almacenes al Costo

## 1. Propósito de la Sección
Esta sección define las reglas operativas, los perfiles de usuario clave y los flujos de trabajo comerciales del sistema. Su objetivo es garantizar que la implementación del software responda fielmente a las decisiones estratégicas de la empresa, sirviendo como la especificación del negocio.

---

## 2. Objetivos
*   Definir con precisión el flujo de compra mediante Checkout Inteligente sin registro obligatorio.
*   Establecer las reglas de procesamiento automático de pagos por pasarela y validación manual de transferencias y Deuna.
*   Modelar los flujos de interacción entre los clientes y el administrador en el procesamiento de pedidos.

---

## 3. Estructura Interna Recomendada de Documentos

Para detallar el modelo de negocio, esta carpeta debe contener los siguientes archivos:

### [01-business-rules.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/01-business/01-business-rules.md)
*   **Propósito**: Recopilar de forma estructurada las reglas que gobiernan el sistema.
*   **Contenido esperado**:
    *   Reglas de facturación e impuestos (IVA aplicado a electrodomésticos y muebles).
    *   Regla de obligatoriedad del comprobante para la aprobación del pedido (solo pagos manuales).
    *   Regla de no-obligatoriedad de registro del cliente para comprar.
    *   Políticas de control de inventario (bloqueo temporal de stock al iniciar pago con tarjeta de 15 minutos o al generar pedido manual vs liberación si no se valida/paga).

### [02-user-personas.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/01-business/02-user-personas.md)
*   **Propósito**: Caracterizar a los usuarios finales del sistema para optimizar los flujos de uso.
*   **Contenido esperado**:
    *   *Cliente*: Perfil de comprador esporádico o recurrente que busca simplicidad, rapidez y seguridad en pagos (tarjeta) o flexibilidad (transferencias).
    *   *Administrador*: Perfil de gestor de tienda enfocado en la validación rápida de pagos manuales, análisis de transacciones de pasarela y actualización del catálogo.

### [03-workflows.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/01-business/03-workflows.md)
*   **Propósito**: Modelar visual y narrativamente los procesos operativos del negocio.
*   **Contenido esperado**:
    *   Diagrama del flujo de compra (Pasarela en línea vs Validación manual).
    *   Flujo alternativo: Pedido rechazado por pasarela o comprobante manual inválido.
    *   Flujo opcional: Registro de cliente tras finalización de compra.

---

## 4. Dependencias con otros Documentos
*   **00-project/README.md**: Hereda el alcance general del MVP y define las prioridades comerciales.
*   **02-requirements/01-functional-requirements.md**: Transforma las reglas y flujos de negocio definidos aquí en requerimientos funcionales técnicos del sistema.
*   **11-decisions/02-adr-pago-manual.md** & **11-decisions/04-adr-integracion-pasarela.md**: Hacen referencia cruzada a las decisiones arquitectónicas para inyectar pasarelas de pago y mantener métodos alternativos manuales.

---

## 5. Observaciones de Inconsistencias
*   *Tiempo límite de validación de comprobante*: El negocio no define cuánto tiempo tiene el cliente para subir el comprobante una vez generado el pedido. Esto genera una inconsistencia con el control de inventario. Si no se define un tiempo de expiración del pedido pendiente, el stock podría quedar bloqueado indefinidamente. (Observación registrada para análisis posterior).
