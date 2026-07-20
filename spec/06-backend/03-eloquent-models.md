# Documento: Modelos Eloquent y Relaciones

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
