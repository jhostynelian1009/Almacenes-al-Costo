# Documento: Plan de Pruebas del Sistema

> **Cobertura vigente:** EP-001 configuración/build/layouts; EP-002 autenticación; EP-004 checkout invitado/stock/orden; EP-005 comprobante, Pago en revisión, autorización, decisión y auditoría. Tarjeta/webhook/HMAC/replay son futuros.

## 1. Objetivos del Documento
Definir la estrategia global de pruebas funcionales y técnicas del sistema **Almacenes al Costo**.

## 2. Estrategia de Pruebas (MVP)
*   **Pruebas Unitarias (Backend)**:
    *   Validar el cálculo correcto de subtotales, IVA y descuentos en el modelo de Pedidos.
    *   Prueba de formato de SKU de productos.
*   **Pruebas de Integración (Feature Tests)**:
    *   Simular el checkout POST de un cliente y validar la inserción correcta de registros en base de datos.
    *   Simular la subida del archivo de comprobante de pago y corroborar que se asocie a la orden y se mueva el archivo físico al storage privado.
*   **Pruebas de Aceptación (Manuales)**:
    *   Validar de forma visual la responsividad de las grillas del catálogo y el flujo de checkout en móviles y tablets.

## 3. Referencias y Dependencias
*   [09-testing/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/09-testing/README.md)
*   [02-requirements/03-use-cases.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/02-requirements/03-use-cases.md)
