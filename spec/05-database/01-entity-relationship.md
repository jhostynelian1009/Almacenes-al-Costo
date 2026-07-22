# Documento: Modelo Entidad-Relación

> **Alineación MVP:** Cliente puede existir sin User y su vínculo es opcional. Entidades de webhook/pasarela son futuras. El MVP requiere pago manual, comprobante, revisor, fecha, observación/rechazo e historial.

## 1. Objetivos del Documento
Definir gráficamente las entidades principales del sistema y sus relaciones para soportar los procesos operativos de la tienda virtual.

> Los estados documentados en este diagrama son la fuente canónica. Toda la documentación usa estos valores exactos.

---

## 2. Diagrama Entidad-Relación (Conceptual)

```mermaid
erDiagram
    USERS ||--o{ ORDERS : "realiza (opcional)"
    CATEGORIES o|--o{ CATEGORIES : "padre de"
    CATEGORIES ||--o{ PRODUCTS : "contiene"
    PRODUCTS ||--|| INVENTORIES : "tiene"
    PRODUCTS ||--o{ ORDER_ITEMS : "incluye"
    ORDERS ||--o{ ORDER_ITEMS : "posee"
    ORDERS }o--o| PROMOTIONS : "aplica (opcional)"
    ORDERS ||--o{ PAYMENTS : "genera"
    ORDERS ||--o| PAYMENT_RECEIPTS : "tiene (si es manual)"
    PAYMENTS ||--o{ PAYMENT_TRANSACTIONS : "registra"
    PAYMENTS ||--o| PAYMENT_RECEIPTS : "vinculado (si es manual)"

    USERS {
        bigint id PK
        string name
        string email UK
        string password
        timestamp created_at
    }
    CATEGORIES {
        bigint id PK
        string name UK
        string slug UK
        text description "nullable"
        string image "nullable"
        string icon "nullable"
        bigint parent_id FK "nullable"
        int display_order "unsigned default 0"
        boolean is_active "default true"
        timestamp created_at
        timestamp updated_at
    }
    PRODUCTS {
        int id PK
        bigint category_id FK
        string name
        string sku UK
        decimal price
        boolean is_active
    }
    INVENTORIES {
        int id PK
        int product_id FK_UK
        int stock
        int reserved_stock
        int min_stock
    }
    PROMOTIONS {
        int id PK
        string code UK
        string discount_type
        decimal discount_value
        boolean is_active
    }
    ORDERS {
        bigint id PK
        bigint user_id FK "nullable"
        int promotion_id FK "nullable"
        string order_number UK
        string customer_name
        string customer_email
        decimal total
        string status "pending_payment | paid | validating | approved | preparing | shipped | delivered | canceled"
    }
    ORDER_ITEMS {
        bigint id PK
        bigint order_id FK
        int product_id FK
        string product_name "snapshot"
        decimal unit_price "snapshot"
        int quantity
        decimal subtotal
    }
    PAYMENTS {
        bigint id PK
        bigint order_id FK
        string gateway "stripe | payphone | datafast | kushki | manual"
        string payment_method "card | transfer | deuna"
        decimal amount
        string currency
        string status "pending | processing | completed | failed | expired | refunded"
        string transaction_id "nullable"
    }
    PAYMENT_TRANSACTIONS {
        bigint id PK
        bigint payment_id FK
        string event_type "request | response | webhook_received | callback_received | error"
        json payload
        timestamp created_at
    }
    PAYMENT_RECEIPTS {
        int id PK
        bigint order_id FK
        bigint payment_id FK "nullable"
        string file_path
        string payment_method "transfer | deuna"
        string transaction_reference "nullable"
        string rejection_reason "nullable"
        timestamp uploaded_at
    }
    WEBHOOK_LOGS {
        bigint id PK
        string gateway
        string event_type
        string event_id UK
        json payload
        boolean signature_verified
        boolean processed
        string processing_error "nullable"
        timestamp created_at
    }
    SETTINGS {
        int id PK
        string key UK
        string value
        string group
    }
```

---

## 3. Notas de Cardinalidad e Integridad

| Relación | Cardinalidad | Nota |
| :--- | :--- | :--- |
| `USERS` → `ORDERS` | 0..N | El usuario es opcional; un cliente invitado tiene `user_id = NULL`. |
| `CATEGORIES` → `CATEGORIES` | 0..N | Una categoría puede tener cero o una categoría padre y múltiples subcategorías. La autorrelación admite múltiples niveles; la aplicación impide autorreferencias y ciclos. |
| `CATEGORIES` → `PRODUCTS` | 0..N | Una categoría puede contener múltiples productos. La eliminación se restringe mientras existan productos asociados. |
| `PRODUCTS` → `INVENTORIES` | 1:1 | Todo producto activo tiene exactamente un registro de inventario. |
| `ORDERS` → `PAYMENTS` | 1..N | Un pedido puede tener múltiples intentos de pago (reintentos). Solo uno puede tener `status = completed`. |
| `PAYMENTS` → `PAYMENT_RECEIPTS` | 0..1 | Solo los pagos manuales tienen un comprobante asociado. |
| `ORDERS` → `PAYMENT_RECEIPTS` | 0..1 | Solo pedidos con método manual tienen comprobante. |
| `ORDERS` → `ORDER_ITEMS` | 1..N | Un pedido tiene al menos un ítem. Los ítems son snapshots inmutables de producto/precio. |
| `WEBHOOK_LOGS` | independiente | No tiene FK a `PAYMENTS` por diseño: el log es previo a la verificación y puede fallar antes de resolverse. |

La eliminación de una categoría se restringe cuando tiene subcategorías o productos asociados. Las categorías inactivas se excluyen del sitio público y las categorías públicas se ordenan por `display_order` y luego por `name`.

---

## 4. Referencias y Dependencias
*   [05-database/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/05-database/README.md)
*   [05-database/02-schema-definition.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/05-database/02-schema-definition.md)
*   [01-business/01-business-rules.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/01-business/01-business-rules.md)
