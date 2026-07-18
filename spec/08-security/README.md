# Sección 08: Políticas y Mecanismos de Seguridad - Almacenes al Costo

## 1. Propósito de la Sección
Esta sección define las políticas y mecanismos técnicos implementados para proteger la integridad del sistema, los datos de los usuarios y la infraestructura del servidor. Su foco principal es la autenticación segura de administradores, el control de acceso y el manejo seguro de archivos subidos por clientes.

---

## 2. Objetivos
*   Establecer el flujo de autenticación seguro para administradores y el registro/login opcional de clientes tras la compra.
*   Definir reglas estrictas para la validación, almacenamiento y acceso a los archivos de comprobante de pago subidos por los clientes.
*   Implementar defensas contra vulnerabilidades comunes de aplicaciones web utilizando las capacidades nativas de Laravel.

---

## 3. Estructura Interna Recomendada de Documentos

Esta carpeta contendrá las especificaciones técnicas sobre la seguridad del sistema:

### [01-auth-authorization.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/08-security/01-auth-authorization.md)
*   **Propósito**: Detallar el control de acceso y roles de usuario.
*   **Contenido esperado**:
    *   *Autenticación Administrativa*: Uso del sistema de autenticación nativo de Laravel (Laravel Breeze, Jetstream o Auth manual) con hash de contraseñas mediante bcrypt.
    *   *Protección de Rutas*: Middleware de autenticación (`auth`) asignado al grupo de rutas `/admin`.
    *   *Autenticación del Cliente (Post-compra)*: Lógica de creación automática de sesión temporal al registrarse opcionalmente en el último paso de la compra.

### [02-upload-security.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/08-security/02-upload-security.md)
*   **Propósito**: Mitigar el riesgo de ejecución de código malicioso mediante la carga de archivos de comprobante.
*   **Contenido esperado**:
    *   *Validación del Archivo*: Reglas de Laravel en la petición (`required|file|image|mimes:jpeg,png,jpg,pdf|max:4096`).
    *   *Almacenamiento Seguro*: Guardar los archivos fuera del directorio público (`storage/app/comprobantes` en lugar de `public/`) para evitar el acceso directo vía URL.
    *   *Control de Acceso al Comprobante*: Ruta del controlador administrativa controlada mediante middleware de autenticación que lea el archivo de forma privada y lo sirva en el panel del administrador (`return Storage::download(...)` o `Storage::response(...)`), evitando exponer la URL directa en Internet.

### [03-data-integrity.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/08-security/03-data-integrity.md)
*   **Propósito**: Explicar los mecanismos de defensa integrados para la base de datos y la aplicación.
*   **Contenido esperado**:
    *   Uso obligatorio del token CSRF en todos los formularios Blade (directiva `@csrf`).
    *   Sanitización de variables en consultas sql (uso obligatorio de Eloquent ORM o Query Builder parametrizado para evitar SQL Injection).
    *   Escapado automático de datos impresos en Blade (uso de `{{ $variable }}`) para prevenir ataques de Cross-Site Scripting (XSS).

---

## 4. Dependencias con otros Documentos
*   **02-requirements/02-non-functional-requirements.md**: Complementa los requerimientos no funcionales de seguridad y los transforma en reglas de código concretas.
*   **06-backend/04-services-helpers.md**: El servicio de subida de archivos debe cumplir estrictamente las reglas definidas en `02-upload-security.md`.
*   **10-deployment/01-env-variables.md**: Las llaves de encriptación (`APP_KEY`) y configuraciones de almacenamiento seguro en producción se controlan en el entorno.

---

## 5. Observaciones de Inconsistencias
*   *Acceso al almacenamiento local*: Se debe asegurar que las instrucciones para Codex especifiquen que **no** se debe hacer público el directorio de comprobantes mediante `php artisan storage:link` para evitar accesos no autorizados a información de transferencias personales de los clientes. El acceso del administrador debe ser estrictamente a través de un endpoint autenticado en el backend.
