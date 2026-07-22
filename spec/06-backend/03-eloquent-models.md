# Documento: Modelos Eloquent y Relaciones

> **Alineación MVP:** Se documentan Customer con User opcional, comprobante y trazabilidad de revisión. `WebhookLog` y modelos exclusivos de proveedor son futuros. Producto e Inventario mantienen responsabilidades separadas.

## 1. Objetivos del Documento
Especificar las clases de modelos de Laravel, sus atributos rellenables y las relaciones ORM necesarias.

## 2. Modelos y Relaciones Clave

### Modelo: `Order`
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id', 'order_number', 'customer_name', 'customer_document',
        'customer_phone', 'customer_email', 'shipping_address',
        'subtotal', 'tax', 'discount', 'total', 'status'
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function receipt()
    {
        return $this->hasOne(PaymentReceipt::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

### Modelo: `Payment`
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'gateway', 'payment_method', 'amount', 'status', 'transaction_id'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function transactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function receipt()
    {
        return $this->hasOne(PaymentReceipt::class);
    }
}
```

### Modelo: `PaymentTransaction`
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $fillable = ['payment_id', 'event_type', 'payload'];

    protected $casts = [
        'payload' => 'array'
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
```

### Modelo: `WebhookLog`
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    protected $fillable = ['gateway', 'event_id', 'payload', 'processed'];

    protected $casts = [
        'payload' => 'array',
        'processed' => 'boolean'
    ];
}
```


### Modelo: `Category`
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'image', 'icon',
        'parent_id', 'display_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('name');
    }
}
```

Una categoría principal tiene `parent_id = NULL`; `parent` y `children` permiten recorrer una jerarquía de múltiples niveles. La capa de aplicación debe impedir que una categoría sea su propio padre, prevenir ciclos y rechazar la eliminación cuando existan subcategorías o productos asociados. El sitio público combina los scopes `active` y `ordered`.

### Modelo: `Product`
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id', 'name', 'sku', 'description', 'price', 'image', 'is_active'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function inventory()
    {
        return $this->hasOne(Inventory::class);
    }
}
```

## 3. Referencias y Dependencias
*   [06-backend/README.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/06-backend/README.md)
*   [05-database/02-schema-definition.md](file:///c:/xampp/htdocs/Almacenes-al-Costo/spec/05-database/02-schema-definition.md)
