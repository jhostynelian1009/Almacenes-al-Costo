# Sección 09: Plan de Pruebas (Testing) - Almacenes al Costo

## 1. Propósito de la Sección
Esta sección establece la metodología y los casos de prueba necesarios para garantizar el correcto funcionamiento del sistema "Almacenes al Costo". Su foco está en asegurar que los flujos críticos de la aplicación, como la creación de pedidos sin cuenta y la validación de pagos, no presenten errores funcionales ni regresiones.

---

## 2. Objetivos
*   Definir la estrategia de control de calidad (QA) del sistema (pruebas automatizadas y manuales).
*   Especificar los casos de prueba unitarios y de integración para validar la lógica del backend y del frontend.
*   Establecer criterios de aceptación claros para cada módulo del cliente y administrador.

---

## 3. Estructura Interna Recomendada de Documentos

Esta carpeta contendrá los siguientes planes de prueba:

### [01-test-plan.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/09-testing/01-test-plan.md)
*   **Propósito**: Definir la estrategia global de pruebas del MVP.
*   **Contenido esperado**:
    *   *Pruebas Unitarias e Integradas*: Uso de PHPUnit (herramienta nativa de Laravel instalada en `tests/Feature` y `tests/Unit`).
    *   *Pruebas Manuales*: Guías de pruebas paso a paso para el flujo visual del cliente desde dispositivos móviles.
    *   *Entornos de Prueba*: Configuración de base de datos sqlite en memoria para ejecución rápida de tests locales (`phpunit.xml`).

### [02-test-cases.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/09-testing/02-test-cases.md)
*   **Propósito**: Listar los escenarios de prueba exactos que deben pasar antes del despliegue.
*   **Contenido esperado**:
    *   *Escenario 1: Flujo de Compra Exitoso (Checkout sin Cuenta)*: Validar que se guarde el pedido, se redireccione a la pantalla de pago y se permita la carga del archivo.
    *   *Escenario 2: Validación de Comprobante por Administrador*: Validar que al dar clic en "Aprobar", el estado del pedido cambie a "Aprobado" y se reduzca el inventario correspondiente.
    *   *Escenario 3: Carga de Archivo Inválido*: Intentar subir un archivo con extensión `.php` o `.exe` y verificar que el sistema lo rechace con una validación de formulario clara.
    *   *Escenario 4: Crear Cuenta Opcional*: Comprobar que el registro posterior a la compra conserve los datos de la orden y cree el usuario correctamente en la tabla `users`.

---

## 4. Dependencias con otros Documentos
*   **02-requirements/01-functional-requirements.md**: Los casos de prueba se redactan directamente a partir del listado de requerimientos funcionales para asegurar una cobertura del 100%.
*   **06-backend/02-controllers.md**: Los tests automatizados simulan las peticiones HTTP que procesan los controladores y validan sus respuestas.
*   **08-security/02-upload-security.md**: El plan de pruebas incluye casos específicos para validar los límites de seguridad en la carga de archivos.

---

## 5. Observaciones de Inconsistencias
*   *Pruebas en Entorno Local Windows*: Se deben diseñar las pruebas automáticas considerando la ruta de almacenamiento de archivos en Windows (ej. compatibilidad de diagonales `/` vs `\`), para asegurar que las pruebas no fallen al ejecutarse en el servidor local XAMPP.
