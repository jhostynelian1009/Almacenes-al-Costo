# Documento: Módulos de Javascript

## 1. Objetivos del Documento
Especificar la lógica del lado del cliente escrita en JavaScript nativo (vanilla JS) para el control del carrito local y dinamismos del formulario.

## 2. Lógica JavaScript Requerida

### `public/js/cart.js` (Carrito local)
*   **Propósito**: Control de almacenamiento en local.
*   **Estructura del Carrito**: Array de objetos `{ id, name, price, quantity, image }` guardado en `localStorage.getItem('cart')`.
*   **Funciones**:
    *   `addToCart(product)`: Agrega o incrementa cantidad en `localStorage`.
    *   `removeFromCart(productId)`: Elimina un producto por ID.
    *   `updateQuantity(productId, quantity)`: Modifica cantidades verificando que sea mayor a 0.
    *   `getCartTotal()`: Calcula el total sumando `price * quantity` de cada elemento.
    *   `renderCartTable()`: Pinta dinámicamente los items en la pantalla `client/cart.blade.php`.

### `public/js/checkout.js`
*   **Propósito**: Vincular el carrito al enviar el formulario, gestionar la pasarela y prevenir dobles envíos.
*   **Lógica**:
    *   *Preparación de Datos*: Al hacer submit, interceptar el formulario, volcar el contenido de `localStorage` del carrito en un campo oculto `<input type="hidden" name="cart_data">` codificado en JSON.
    *   *Prevención de Doble Clic (Anti-duplicados)*: Inmediatamente al presionar "Generar Pedido", deshabilitar el botón de envío (`disabled = true`) e inyectar un spinner Bootstrap (`<span class="spinner-border spinner-border-sm"></span>`) con el texto "Procesando pago...".
    *   *Manejo de Pasarelas / Redirección*: 
        *   Si se selecciona pago con Tarjeta, realizar petición AJAX (`fetch`) a la ruta del checkout. Al recibir el JSON de respuesta con la URL de redirección o tokens seguros, iniciar el modal del proveedor (ej. Stripe o PayPhone iframe) o redirigir al portal.
        *   *Vaciado del Carrito*: Solo vaciar el `localStorage` mediante `localStorage.removeItem('cart')` una vez que la orden se ha registrado con éxito en el backend (y antes de redirigir al portal de pago o a la vista de confirmación manual).

## 3. Referencias y Dependencias
*   [07-frontend/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/07-frontend/README.md)
*   [06-backend/02-controllers.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/06-backend/02-controllers.md)
