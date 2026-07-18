# Sección 10: Configuración y Despliegue - Almacenes al Costo

## 1. Propósito de la Sección
Esta sección detalla los requerimientos de infraestructura y los pasos necesarios para instalar, configurar y desplegar el sistema en entornos de desarrollo y producción. Dado el entorno del usuario, hace énfasis en la puesta en marcha local utilizando XAMPP en Windows y la preparación del entorno productivo.

---

## 2. Objetivos
*   Definir la estructura y el propósito de las variables de entorno configuradas en el archivo `.env`.
*   Proporcionar una guía de despliegue paso a paso para desarrolladores y administradores del sistema.
*   Especificar los comandos necesarios para la inicialización y mantenimiento de la base de datos en producción.

---

## 3. Estructura Interna Recomendada de Documentos

Esta carpeta contendrá los siguientes manuales de operaciones:

### [01-env-variables.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/10-deployment/01-env-variables.md)
*   **Propósito**: Documentar cada variable de entorno de Laravel requerida para el funcionamiento del sistema.
*   **Contenido esperado**:
    *   Configuración de la Base de Datos (`DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
    *   Configuración del Driver de Almacenamiento (`FILESYSTEM_DISK=local` para proteger comprobantes).
    *   Configuración del Modo Debug y Entorno (`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`).

### [02-deployment-guide.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/10-deployment/02-deployment-guide.md)
*   **Propósito**: Detallar el paso a paso de la instalación y actualización del sistema.
*   **Contenido esperado**:
    *   *Prerrequisitos*: Versión de PHP compatible (según composer.json), MySQL habilitado en XAMPP.
    *   *Paso a paso*:
        1. Clonación del repositorio.
        2. Ejecución de `composer install --no-dev --optimize-autoloader`.
        3. Configuración del archivo `.env` a partir del `.env.example`.
        4. Generación de la llave de aplicación: `php artisan key:generate`.
        5. Ejecución de migraciones y semillas: `php artisan migrate --force --seed`.
        6. Creación del enlace simbólico público si se requiere para assets: `php artisan storage:link`.
        7. Configuración del servidor Apache en XAMPP (DocumentRoot apuntando a la carpeta `/public` del proyecto).

---

## 4. Dependencias con otros Documentos
*   **03-architecture/03-directory-structure.md**: La guía de despliegue depende del mapeo de directorios físicos de Laravel para configurar los permisos de escritura en `/storage` y `/bootstrap/cache`.
*   **05-database/04-seeders-migrations.md**: La inicialización de la base de datos durante el despliegue requiere ejecutar las migraciones y semillas especificadas en ese documento.
*   **08-security/02-upload-security.md**: El despliegue debe contemplar los permisos correctos en las carpetas de comprobantes para evitar brechas de seguridad.

---

## 5. Observaciones de Inconsistencias
*   *Configuración de Enlace Simbólico*: Tradicionalmente en Laravel se ejecuta `php artisan storage:link` para exponer la carpeta `storage/app/public`. Sin embargo, tal como se mencionó en la sección de seguridad, los comprobantes no deben estar en la carpeta pública. La guía de despliegue debe alertar explícitamente al administrador de **no** colocar la carpeta de comprobantes dentro de la ruta pública expuesta por el enlace simbólico.
*   *Versión de PHP*: Se debe verificar la compatibilidad de la versión de PHP en XAMPP instalada localmente con los requisitos especificados en `composer.json` antes de iniciar la instalación de dependencias.
