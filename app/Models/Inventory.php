<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    public const CREATED_AT = null;

    public const UPDATED_AT = 'updated_at';

    protected $table = 'inventories';

    protected $fillable = [
        'product_id',
        'stock',
        'reserved_stock',
        'min_stock',
    ];

    protected static function booted(): void
    {
        static::saving(function (Inventory $inventory): void {
            $quantities = collect(['stock', 'reserved_stock', 'min_stock'])
                ->mapWithKeys(fn (string $attribute): array => [
                    $attribute => $inventory->validatedQuantity($attribute),
                ]);

            if ($quantities['reserved_stock'] > $quantities['stock']) {
                throw new DomainException('El stock reservado no puede superar el stock físico.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stock' => 'integer',
            'reserved_stock' => 'integer',
            'min_stock' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    protected function availableStock(): Attribute
    {
        return Attribute::get(
            fn (): int => $this->stock - $this->reserved_stock,
        );
    }

    private function validatedQuantity(string $attribute): int
    {
        $value = $this->getAttributes()[$attribute] ?? 0;

        if (! is_int($value) && (! is_string($value) || preg_match('/\A\d+\z/', $value) !== 1)) {
            throw new DomainException('Las cantidades de inventario deben ser números enteros no negativos.');
        }

        $quantity = (int) $value;

        if ($quantity < 0) {
            throw new DomainException('Las cantidades de inventario no pueden ser negativas.');
        }

        return $quantity;
    }
}
