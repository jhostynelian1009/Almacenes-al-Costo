# Documento: Autenticación y Autorización

> **Distribución vigente:** EP-002 contiene autenticación base, sesiones, roles iniciales y panel protegido; EP-014 la administración avanzada. El checkout invitado no requiere autenticación.

## 1. Objetivos del Documento
Especificar los flujos de inicio de sesión de administradores y el control de accesos a las diferentes secciones protegidas del sistema.

## 2. Autenticación y Control de Accesos
*   **Autenticación de Administradores**:
    *   Formulario en `/admin/login`.
    *   Uso del framework nativo `Auth` de Laravel.
    *   Las contraseñas de los usuarios en `users` deben estar encriptadas usando la función `Hash::make()` (bcrypt).
*   **Guardias y Middleware**:
    *   El middleware `auth` se debe asociar al grupo de rutas `/admin/*`.
    *   Cualquier petición no autenticada a `/admin/*` debe ser redireccionada automáticamente a la pantalla de login `/admin/login`.
*   **Autenticación opcional del Cliente**:
    *   Si el cliente ingresa una contraseña en la confirmación post-checkout, se guarda el registro y se loguea al usuario usando `Auth::login($user)`.

## 3. Referencias y Dependencias
*   [08-security/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/08-security/README.md)
*   [06-backend/01-routes-map.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/06-backend/01-routes-map.md)
