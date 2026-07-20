# Documento: Estilos Personalizados y Bootstrap 5

## 1. Objetivos del Documento
Especificar las clases de personalización de CSS necesarias para complementar Bootstrap 5 y lograr una identidad premium para **Almacenes al Costo**.

## 2. Clases CSS a Implementar (Ejemplos)
*   **Variables Bootstrap Personalizadas y Clases de Color**:
    ```css
    :root {
        --color-crema-bg: #FDFBF7;
        --color-marfil-card: #FAF9F6;
        --color-naranja-brand: #F26419;
        --color-durazno-action: #F7A072;
        --color-cafe-dark: #3D312A;
    }

    body {
        background-color: var(--color-crema-bg);
        color: var(--color-cafe-dark);
        font-family: 'Outfit', 'Inter', sans-serif;
    }
    ```

*   **Tarjetas Premium (Estilo Marfil)**:
    ```css
    .card-premium {
        background-color: var(--color-marfil-card);
        border: 1px solid rgba(61, 49, 42, 0.05);
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(61, 49, 42, 0.04);
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }
    .card-premium:hover {
        transform: translateY(-6px);
        box-shadow: 0 12px 32px rgba(61, 49, 42, 0.08);
    }
    ```

*   **Botones Modernos (Estilo Naranja Cálido)**:
    ```css
    .btn-action-orange {
        background-color: var(--color-naranja-brand);
        color: #FFFFFF;
        border-radius: 12px;
        font-weight: 600;
        border: none;
        padding: 10px 24px;
        transition: background-color 0.2s ease, transform 0.1s ease;
    }
    .btn-action-orange:hover {
        background-color: var(--color-durazno-action);
        color: var(--color-cafe-dark);
    }
    .btn-action-orange:active {
        transform: scale(0.98);
    }
    ```


## 3. Referencias y Dependencias
*   [07-frontend/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/07-frontend/README.md)
*   [04-ui-ux/02-design-system.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/04-ui-ux/02-design-system.md)
