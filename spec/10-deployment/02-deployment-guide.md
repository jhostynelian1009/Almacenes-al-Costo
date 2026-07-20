# Documento: Guía de Instalación y Despliegue

## 1. Objetivos del Documento
Guiar paso a paso en la instalación inicial y puesta en producción local o remota del proyecto **Almacenes al Costo**.

## 2. Pasos para Instalación Local (Entorno XAMPP en Windows)
1.  **Activar Servicios**: Abrir el panel de control de XAMPP e iniciar los servicios de Apache y MySQL.
2.  **Crear Base de Datos**: Ingresar a phpMyAdmin y crear una base de datos vacía llamada `almacenes_al_costo` con cotejamiento `utf8mb4_unicode_ci`.
3.  **Configurar Archivo de Entorno**: Copiar `.env.example` como `.env` y configurar las credenciales de la base de datos (usualmente usuario `root` y contraseña vacía en XAMPP).
4.  **Instalar Dependencias**: Ejecutar `composer install` y `npm install && npm run build` (o compilación correspondiente).
5.  **Generar Clave de Encriptación**: Ejecutar `php artisan key:generate`.
6.  **Migrar y Sembrar**: Ejecutar `php artisan migrate --seed` para crear las tablas e inyectar el administrador y categorías semilla.
7.  **Servidor Web**: Configurar un Host Virtual en Apache de XAMPP apuntando el directorio raíz a la carpeta `/public` del proyecto, o ejecutar `php artisan serve` para testeo rápido.

## 3. Referencias y Dependencias
*   [10-deployment/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/10-deployment/README.md)
*   [05-database/04-seeders-migrations.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/05-database/04-seeders-migrations.md)
