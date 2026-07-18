# Registro de Decisión: Checkout Inteligente sin Registro Obligatorio

## 1. Contexto y Problema
Exigir a los clientes crear una cuenta antes de realizar la compra genera una alta tasa de abandono en el carrito de compras, especialmente en la venta de electrodomésticos y muebles de hogar donde el ticket promedio es alto y las compras no son cotidianas.

## 2. Decisión Tomada
*   **Estado**: Aceptado (Inmutable).
*   **Detalle**: Los clientes completarán la transacción ingresando únicamente sus datos de entrega y contacto en el Checkout. El registro de cuenta será enteramente opcional y se ofrecerá al finalizar el flujo de compra.

## 3. Consecuencias
*   *Positivo*: Disminución en la fricción del checkout, incremento potencial en conversiones.
*   *Negativo*: La base de datos debe contemplar datos de envío directamente en la tabla de pedidos, en lugar de vincularlos únicamente a un perfil de usuario preexistente.

## 4. Referencias y Dependencias
*   [11-decisions/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/11-decisions/README.md)
*   [01-business/01-business-rules.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/01-business/01-business-rules.md)
