# Documento: Requerimientos No Funcionales

## 1. Objetivos del Documento
Especificar los estándares técnicos de calidad, rendimiento y operabilidad bajo los cuales debe funcionar la plataforma **Almacenes al Costo**.

## 2. Requerimientos No Funcionales (RNF)
*   **RNF-01 (Rendimiento)**: Las consultas del catálogo e imágenes optimizadas deben cargarse en un tiempo promedio inferior a 2 segundos con conexiones móviles promedio.
*   **RNF-02 (Responsive)**: La interfaz del cliente debe diseñarse bajo enfoque "Mobile First" garantizando operatividad total en pantallas de teléfonos Android e iOS.
*   **RNF-03 (Seguridad de Datos)**: Todos los datos confidenciales de transacciones deben almacenarse de forma protegida. El panel administrativo debe forzar contraseñas robustas y expirar sesiones inactivas.
*   **RNF-04 (Mantenibilidad)**: El código debe seguir los estándares de desarrollo de Laravel PSR-12 y mantener la separación de responsabilidades en la arquitectura MVC.
*   **RNF-05 (Compatibilidad de Servidor)**: Debe ejecutarse correctamente sobre PHP 8.x, base de datos MySQL 8.x en entornos locales como XAMPP sobre Windows.

## 3. Referencias y Dependencias
*   [02-requirements/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/02-requirements/README.md)
*   [08-security/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/08-security/README.md)
*   [10-deployment/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/10-deployment/README.md)
