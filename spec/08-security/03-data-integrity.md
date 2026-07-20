# Documento: Integridad y Protección de Datos del Negocio

> **Alineación MVP:** idempotencia administrativa/transiciones manuales sí aplican; idempotencia externa y replay protection son futuros. EP-004 crea la orden y EP-009 administra estados.

## 1. Objetivos del Documento
Garantizar la protección del sistema contra ataques y vulnerabilidades web OWASP Top 10 utilizando recursos nativos de Laravel.

## 2. Controles de Integridad de Datos
*   **Ataques CSRF (Cross-Site Request Forgery)**:
    *   Toda petición POST, PUT o DELETE a través de formularios Blade debe incluir la directiva `@csrf` para adjuntar el token de verificación de sesión.
*   **Inyección SQL**:
    *   Queda estrictamente prohibido concatenar variables en consultas SQL crudas.
    *   Toda consulta debe estructurarse mediante los métodos de enlace de parámetros de Eloquent o Query Builder (`where()`, `find()`, etc.).
*   **Ataques XSS (Cross-Site Scripting)**:
    *   Los datos mostrados en vistas Blade ingresados por los usuarios deben imprimirse mediante llaves dobles estándar `{{ $variable }}`, las cuales escapan automáticamente cualquier código HTML o JavaScript.
    *   El uso de `{!! $variable !!}` debe limitarse únicamente a textos administrados confiables y previamente sanitizados.

## 3. Referencias y Dependencias
*   [08-security/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/08-security/README.md)
*   [02-requirements/02-non-functional-requirements.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/02-requirements/02-non-functional-requirements.md)
