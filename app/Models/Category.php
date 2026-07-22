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

    /**
     * @return array<int, int>
     */
    public function descendantIds(): array
    {
        if (! $this->exists) {
            return [];
        }

        $childrenByParent = static::query()
            ->get(['id', 'parent_id'])
            ->groupBy(fn (Category $category): string => (string) $category->parent_id);

        $descendantIds = [];
        $visited = [(string) $this->getKey() => true];
        $pending = $childrenByParent
            ->get((string) $this->getKey(), collect())
            ->pluck('id')
            ->all();

        while ($pending !== []) {
            $categoryId = (int) array_pop($pending);
            $key = (string) $categoryId;

            if (isset($visited[$key])) {
                continue;
            }

            $visited[$key] = true;
            $descendantIds[] = $categoryId;

            foreach ($childrenByParent->get($key, collect()) as $child) {
                $pending[] = $child->getKey();
            }
        }

        return $descendantIds;
    }

    public function wouldCreateCycle(?int $parentId): bool
    {
        if ($parentId === null || ! $this->exists) {
            return false;
        }

        if ((string) $parentId === (string) $this->getKey()) {
            return true;
        }

        return in_array($parentId, $this->descendantIds(), true);
    }

    private function ensureValidStructure(): void
    {
        if ($this->parent_id !== null && $this->getKey() !== null && (string) $this->parent_id === (string) $this->getKey()) {
            throw new LogicException('A category cannot be its own parent.');
        }

        if ($this->isDirty('parent_id') && $this->wouldCreateCycle($this->parent_id === null ? null : (int) $this->parent_id)) {
            throw new LogicException('A category cannot use one of its descendants as parent.');
        }

        if ($this->display_order !== null && $this->display_order < 0) {
            throw new LogicException('The category display order cannot be negative.');
        }
    }
}
