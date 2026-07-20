# Sección 03: Arquitectura del Sistema - Almacenes al Costo

## 1. Propósito de la Sección
Esta sección define las directrices y decisiones arquitectónicas del sistema. Su objetivo es estructurar la plataforma web para que sea mantenible, escalable y esté preparada para futuras evoluciones (como la integración con pasarelas de pago automatizadas), alineándose a las convenciones de Laravel.

---

## 2. Objetivos
*   Definir la arquitectura de software general basada en el patrón MVC (Modelo-Vista-Controlador) de Laravel.
*   Establecer la estructura física de directorios y la organización lógica del código.
*   Estructurar e implementar un sistema de pagos desacoplado desde la versión 1 (MVP) utilizando patrones Strategy y Adapter.

---

## 3. Estructura Interna Recomendada de Documentos

Esta carpeta contendrá los siguientes documentos sobre la arquitectura del software:

### [01-system-architecture.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/03-architecture/01-system-architecture.md)
*   **Propósito**: Describir la arquitectura técnica general del sistema.
*   **Contenido esperado**:
    *   Arquitectura monolítica basada en Laravel, Blade y Bootstrap.
    *   Estrategia de desacoplamiento para pagos: Uso de los patrones de diseño *Strategy* y *Adapter* integrados a través de `PaymentService` y `PaymentGatewayInterface` para dar soporte a Stripe, PayPhone, Datafast, Kushki y pagos manuales desde la primera versión, sin acoplar la lógica del Checkout.

### [02-integration-diagrams.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/03-architecture/02-integration-diagrams.md)
*   **Propósito**: Modelar la interacción de datos y la comunicación entre los componentes del sistema.
*   **Contenido esperado**:
    *   Diagramas de secuencia que muestren el recorrido de la información desde que el cliente interactúa con el carrito hasta que el administrador valida el pedido.
    *   Mapeo de integraciones de almacenamiento (Laravel Storage) para los comprobantes cargados por los clientes.

### [03-directory-structure.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/03-architecture/03-directory-structure.md)
*   **Propósito**: Especificar la ubicación física de las clases y recursos principales dentro de la estructura estándar de Laravel.
*   **Contenido esperado**:
    *   Ubicación de Controladores (`app/Http/Controllers`).
    *   Ubicación de Modelos y Migraciones (`app/Models`, `database/migrations`).
    *   Ubicación de Vistas y Assets (`resources/views`, `public/assets`).
    *   Estructura recomendada para el almacenamiento de archivos de comprobante (`storage/app/comprobantes`).

---

## 4. Dependencias con otros Documentos
*   **02-requirements/01-functional-requirements.md**: La arquitectura debe proveer los componentes necesarios para satisfacer todos los requerimientos listados.
*   **05-database/README.md**: La capa de Modelo de la arquitectura MVC se mapea directamente a la definición del esquema de la base de datos MySQL.
*   **06-backend/README.md** & **07-frontend/README.md**: Definen la implementación detallada de las capas controladoras, lógicas y vistas que componen esta arquitectura.

---

## 5. Observaciones de Inconsistencias
*   *Desacoplamiento de pasarelas de pago*: Aunque la versión 1 (MVP) solo requiere pagos manuales por transferencia y Deuna, la arquitectura debe forzar la creación de interfaces de pago independientes para evitar el acoplamiento directo de la base de datos de pedidos a flujos manuales de imágenes. Se recomienda abstraer el estado del pago y su validación.
