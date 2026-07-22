<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'icon',
        'parent_id',
        'display_order',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::saving(function (Category $category): void {
            $category->ensureValidStructure();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    private function ensureValidStructure(): void
    {
        if ($this->parent_id !== null && $this->getKey() !== null && (string) $this->parent_id === (string) $this->getKey()) {
            throw new LogicException('A category cannot be its own parent.');
        }

        if ($this->display_order !== null && $this->display_order < 0) {
            throw new LogicException('The category display order cannot be negative.');
        }
    }
}
