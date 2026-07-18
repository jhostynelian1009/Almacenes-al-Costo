# Documento: Configuración de Variables de Entorno

## 1. Objetivos del Documento
Especificar y explicar las variables críticas del archivo `.env` necesarias para el correcto comportamiento del sistema **Almacenes al Costo**.

## 2. Variables de Entorno Clave
*   `APP_NAME="Almacenes al Costo"`: Nombre del sistema.
*   `APP_ENV=local` / `production`: Determina el modo de depuración y nivel de seguridad.
*   `APP_KEY=base64:...`: Clave de encriptación de Laravel (se genera con `php artisan key:generate`).
*   `APP_DEBUG=true` / `false`: Debe estar en `false` en entornos de producción.
*   `DB_CONNECTION=mysql`: Conexión a la base de datos relacional.
*   `DB_HOST=127.0.0.1`: Host de MySQL (típicamente localhost en XAMPP).
*   `DB_DATABASE=almacenes_al_costo`: Nombre del esquema de la base de datos.
*   `FILESYSTEM_DISK=local`: Forzar el almacenamiento de archivos de forma privada.

*   **Configuración de Pasarela por Defecto**:
    *   `PAYMENT_GATEWAY_DEFAULT=stripe` (Pasarela predeterminada activa: 'stripe', 'payphone', 'datafast', 'kushki', o 'manual').

*   **Credenciales de Stripe**:
    *   `STRIPE_KEY=pk_test_...` (Clave pública de Stripe para el frontend).
    *   `STRIPE_SECRET=sk_test_...` (Clave secreta de Stripe para peticiones del servidor).
    *   `STRIPE_WEBHOOK_SECRET=whsec_...` (Firma digital para validación de webhooks de Stripe).

*   **Credenciales de PayPhone**:
    *   `PAYPHONE_TOKEN=...` (Token de autenticación Bearer de PayPhone).
    *   `PAYPHONE_STORE_ID=...` (ID de la tienda física configurada en el portal de PayPhone).

*   **Credenciales de Datafast**:
    *   `DATAFAST_CLIENT_ID=...` (ID del cliente asignado por Datafast / Banco).
    *   `DATAFAST_CLIENT_SECRET=...` (Clave de firma para Datafast).

*   **Credenciales de Kushki**:
    *   `KUSHKI_PUBLIC_KEY=...` (Token público de enrolamiento de tarjetas).
    *   `KUSHKI_PRIVATE_KEY=...` (Token privado para cobros e indemnizaciones).

## 3. Referencias y Dependencias
*   [10-deployment/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/10-deployment/README.md)
*   [08-security/02-upload-security.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/08-security/02-upload-security.md)
