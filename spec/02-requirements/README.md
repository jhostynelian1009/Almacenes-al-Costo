# Sección 02: Especificación de Requerimientos - Almacenes al Costo

## 1. Propósito de la Sección
Esta sección define formalmente qué debe hacer el sistema (Requerimientos Funcionales) y bajo qué restricciones o estándares de calidad debe operar (Requerimientos No Funcionales). Traduce las necesidades de negocio en especificaciones estructuradas para el desarrollo backend y frontend.

---

## 2. Objetivos
*   Detallar los requerimientos funcionales agrupados por los 10 módulos del cliente y los 13 módulos del administrador (0 a 12).
*   Especificar los atributos de calidad (rendimiento, seguridad, disponibilidad, usabilidad) requeridos para el MVP.
*   Presentar los casos de uso principales para estructurar el desarrollo del código y las pruebas de aceptación.

---

## 3. Estructura Interna Recomendada de Documentos

Esta carpeta contendrá los siguientes documentos de especificación técnica:

### [01-functional-requirements.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/02-requirements/01-functional-requirements.md)
*   **Propósito**: Listar de forma numerada e inequívoca todos los requerimientos funcionales (RF).
*   **Contenido esperado**:
    *   *RF-CLIENTE-01 al RF-CLIENTE-10*: Requerimientos detallados para cada uno de los 10 módulos del cliente (ej. carrito local persistente, formulario de checkout con campos validados, carga de archivos PDF/JPG para comprobantes, etc.).
    *   *RF-ADMIN-00 al RF-ADMIN-12*: Requerimientos detallados para cada uno de los 13 módulos del administrador (ej. autenticación, ABM de productos con carga de imágenes, control de stock y alertas de mínimo, módulo de visualización de comprobante y cambio de estado del pedido).

### [02-non-functional-requirements.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/02-requirements/02-non-functional-requirements.md)
*   **Propósito**: Listar los requerimientos no funcionales (RNF) que garantizan la calidad del software.
*   **Contenido esperado**:
    *   *Rendimiento*: Tiempo de respuesta del catálogo e inicio menor a 2 segundos.
    *   *Seguridad*: Encriptación de contraseñas de administrador, protección CSRF y sanitización de entradas.
    *   *Compatibilidad*: Diseño totalmente responsive (Bootstrap 5) optimizado para dispositivos móviles y de escritorio.
    *   *Portabilidad*: Facilidad de instalación en entornos Windows con XAMPP (requerido por el entorno del usuario).

### [03-use-cases.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/02-requirements/03-use-cases.md)
*   **Propósito**: Describir la interacción detallada entre el usuario y el sistema para flujos críticos.
*   **Contenido esperado**:
    *   *Caso de Uso 1*: Cliente realiza un pedido y sube el comprobante sin estar registrado.
    *   *Caso de Uso 2*: Administrador valida el comprobante de pago y aprueba el pedido.
    *   *Caso de Uso 3*: Cliente decide crear una cuenta después de que su pedido fue aprobado.

---

## 4. Dependencias con otros Documentos
*   **01-business/01-business-rules.md**: Los requerimientos funcionales deben reflejar y hacer cumplir estrictamente las reglas de negocio establecidas.
*   **04-ui-ux/03-screen-specs.md**: Cada requerimiento funcional debe encontrar su correlato visual en una pantalla del sistema.
*   **09-testing/02-test-cases.md**: Los requerimientos funcionales y casos de uso sirven como base directa para la redacción de los casos de prueba.

---

## 5. Observaciones de Inconsistencias
*   *Módulo Reportes y Clientes*: El prompt especifica los módulos del administrador "8. Clientes" y "10. Reportes", pero no detalla qué acciones específicas o métricas se deben contemplar en el MVP. (Se asume un listado de clientes básico y reportes de facturación sencillos para evitar sobredimensionar el proyecto en la V1).
*   *Módulo de Promociones*: Se incluye "9. Promociones" en el administrador, pero no hay un módulo correlativo de cupones o descuentos en el cliente (ej. aplicar cupón en el Carrito o Checkout). (Se marca como observación para definir el requerimiento del descuento en el carrito del cliente).
