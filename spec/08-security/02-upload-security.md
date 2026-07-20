# Documento: Seguridad del Sistema

## 1. Objetivos del Documento
Establecer los controles de seguridad técnica requeridos para proteger el sistema **Almacenes al Costo** contra vulnerabilidades web comunes y garantizar la integridad de las transacciones de pago.

---

## 2. Control de Acceso y Autenticación

*   **Acceso Administrativo**: La ruta `/admin/*` está protegida por el middleware `auth`. Solo usuarios con registro en la tabla `users` pueden acceder. No hay roles múltiples en V1.0 — solo existe el perfil administrador.
*   **Acceso del Cliente**: El checkout no requiere autenticación. El cliente es invitado hasta que opta por crear cuenta post-compra (RN-01, RN-05).
*   **Sesiones Seguras**: Las sesiones se gestionan con el driver nativo de Laravel. En producción, configurar `SESSION_SECURE_COOKIE=true` y `SESSION_SAME_SITE=lax`.

---

## 3. HTTPS Obligatorio

*   Todo el tráfico entre el cliente, el servidor Laravel y las APIs de pasarelas de pago debe transmitirse bajo **HTTPS (TLS 1.2 o superior)**.
*   En producción, configurar Laravel con `FORCE_HTTPS=true` y usar el middleware `ForceHttps` o configuración de servidor (Nginx/Apache).
*   Los webhooks de pasarelas **solo** se aceptan sobre HTTPS. Requests HTTP sin cifrado deben rechazarse en el servidor.

---

## 4. Protección CSRF

*   Todos los formularios Blade que realizan `POST`, `PUT` o `DELETE` deben incluir la directiva `@csrf`.
*   **Excepción crítica — Webhooks**: El endpoint `POST /api/payment/webhook/{gateway}` debe ser excluido de la verificación CSRF, ya que la pasarela es un servidor externo que no posee el token de sesión.
    *   Configurar en `App\Http\Middleware\VerifyCsrfToken::$except`:
        ```
        protected $except = [
            'api/payment/webhook/*',
        ];
        ```
    *   La seguridad del webhook en reemplazo del CSRF se garantiza por la **validación de firma HMAC** (ver `03-architecture/04-webhooks.md`).

---

## 5. Prevención de Inyección SQL

*   Queda **estrictamente prohibido** concatenar variables de entrada del usuario en consultas SQL crudas.
*   Toda consulta debe usarse mediante los métodos de Eloquent ORM o Query Builder con binding de parámetros (`where()`, `find()`, `whereIn()`, etc.).
*   Si se requiere SQL crudo por motivos de rendimiento, usar `DB::select('SELECT * FROM orders WHERE id = ?', [$id])` con binding posicional.

---

## 6. Prevención de XSS (Cross-Site Scripting)

*   Los datos de usuario mostrados en vistas Blade deben imprimirse siempre con `{{ $variable }}`, que escapa automáticamente HTML y JavaScript.
*   El uso de `{!! $variable !!}` (salida sin escapar) está **prohibido** para datos provenientes de usuarios. Solo se permite para contenido administrativo previamente sanitizado con `strip_tags()` o `Purifier`.
*   Cabeceras de seguridad recomendadas en producción: `Content-Security-Policy`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`.

---

## 7. Carga Segura de Comprobantes

### 7.1 Validación de Archivos
*   **No confiar en la extensión** provista por el cliente (`getClientOriginalExtension()`).
*   Forzar validación de **tipo MIME real** del archivo usando el driver de detección del sistema: `image/jpeg`, `image/png`, `application/pdf`.
*   Tamaño máximo: **4MB** (regla Laravel: `max:4096`).

### 7.2 Almacenamiento
*   Guardar **exclusivamente** en `storage/app/comprobantes/` (disco `local`, privado).
*   **Nunca** mover archivos a `public/storage/` ni crear symlinks a esta carpeta.
*   Nombre del archivo generado por el sistema (hash + order_number), nunca el nombre original del cliente.

### 7.3 Acceso para el Administrador
*   La visualización del comprobante en el panel admin se realiza mediante un endpoint Laravel protegido: `GET /admin/orders/{id}/receipt`.
*   Este endpoint lee el archivo desde storage y retorna una respuesta binaria con `Storage::response($path)`.
*   El administrador nunca accede directamente al sistema de archivos.

---

## 8. Seguridad de Pagos y PCI-DSS

### 8.1 No Almacenamiento de Datos Sensibles de Tarjetas
*   El servidor de **Almacenes al Costo** **no debe** recibir, transmitir, almacenar ni procesar el número de tarjeta (PAN), fecha de expiración ni CVV.
*   Los datos de tarjeta son ingresados directamente en la infraestructura certificada de la pasarela (Stripe Elements, PayPhone Iframe, etc.), que retorna únicamente un token de un solo uso.

### 8.2 Validación de Webhooks (Anti-fraude)
*   Todos los webhooks deben validar su firma criptográfica **antes** de procesar cualquier dato.
*   Especificación completa en [03-architecture/04-webhooks.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/03-architecture/04-webhooks.md).

### 8.3 Prevención de Manipulación de Importes
*   Los montos del pedido se calculan **exclusivamente en el servidor** (`PaymentService::initialize`) consultando precios desde la base de datos.
*   El frontend no puede enviar el monto total como parámetro modificable. Solo envía el `order_number`; el servidor recalcula el total antes de enviarlo a la pasarela.

### 8.4 Idempotencia Anti-Cobro-Duplicado
*   La tabla `webhook_logs` con `event_id UNIQUE` previene que un mismo evento de cobro se procese más de una vez.
*   Especificación en [03-architecture/04-webhooks.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/03-architecture/04-webhooks.md) — Sección 6.

---

## 9. Matriz de Controles de Seguridad

| Amenaza | Control implementado | Dónde se especifica |
| :--- | :--- | :--- |
| Acceso no autorizado al admin | Middleware `auth` en rutas `/admin/*` | `06-backend/01-routes-map.md` |
| CSRF en formularios | Directiva `@csrf` en Blade | Sección 4 de este documento |
| CSRF en webhook (falso positivo) | Exclusión explícita + validación HMAC | Sección 4 + `03-architecture/04-webhooks.md` |
| Inyección SQL | Eloquent ORM + binding | Sección 5 de este documento |
| XSS | Escapado automático `{{ }}` en Blade | Sección 6 de este documento |
| Malware en comprobantes | Validación MIME real + storage privado | Sección 7 de este documento |
| Robo de datos de tarjeta | Tokenización en frontend de la pasarela (PCI-DSS) | Sección 8.1 de este documento |
| Webhooks falsos | Validación de firma HMAC por proveedor | `03-architecture/04-webhooks.md` |
| Cobros duplicados | `event_id UNIQUE` en `webhook_logs` | `05-database/02-schema-definition.md` |
| Manipulación de precios | Montos calculados en servidor | Sección 8.3 de este documento |
| Replay Attack en webhooks | Validación de timestamp + idempotencia | `03-architecture/04-webhooks.md` |
| Comunicación sin cifrar | HTTPS obligatorio en todas las rutas | Sección 3 de este documento |

---

## 10. Referencias y Dependencias
*   [08-security/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/08-security/README.md)
*   [03-architecture/04-webhooks.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/03-architecture/04-webhooks.md)
*   [06-backend/04-services-helpers.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/06-backend/04-services-helpers.md)
*   [02-requirements/02-non-functional-requirements.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/02-requirements/02-non-functional-requirements.md)
