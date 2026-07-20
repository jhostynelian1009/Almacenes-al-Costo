# Registro de Decisión: Selección del Stack Tecnológico

## 1. Contexto y Problema
El sistema debe ser fácil de mantener, veloz en su implementación y de instalación simplificada en servidores locales de Windows con XAMPP.

## 2. Decisión Tomada
*   **Estado**: Aceptado (Inmutable).
*   **Detalle**: Utilizar PHP con el framework Laravel para el backend y modelado de datos MySQL. Las interfaces de usuario se renderizarán a través del motor de plantillas Blade e interacciones en JavaScript nativo estructuradas sobre Bootstrap 5.

## 3. Consecuencias
*   *Positivo*: Simplicidad en arquitectura, alta disponibilidad de documentación y facilidad de puesta en marcha local mediante XAMPP.
*   *Negativo*: Toda interactividad avanzada del frontend (ej. carrito en tiempo real) debe ser programada con JavaScript nativo en lugar de aprovechar el reactivismo out-of-the-box de frameworks SPA como React o Vue.

## 4. Referencias y Dependencias
*   [11-decisions/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/11-decisions/README.md)
*   [00-project/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/00-project/README.md)
*   [07-frontend/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/07-frontend/README.md)
