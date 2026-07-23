<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Str;

class ProductSlugService
{
    public const MAX_LENGTH = 255;

    public function generate(string $name): string
    {
        $base = $this->baseSlug($name);
        $candidate = $base;
        $suffix = 1;

        while (Product::withTrashed()->where('slug', $candidate)->exists()) {
            $suffix++;
            $candidate = $this->withSuffix($base, $suffix);
        }

        return $candidate;
    }

    private function baseSlug(string $name): string
    {
        $slug = Str::slug(trim($name));

        if ($slug === '') {
            $slug = 'producto';
        }

        return rtrim(Str::substr($slug, 0, self::MAX_LENGTH), '-');
    }

    private function withSuffix(string $base, int $number): string
    {
        $suffix = '-'.$number;
        $availableLength = self::MAX_LENGTH - Str::length($suffix);
        $truncatedBase = rtrim(Str::substr($base, 0, $availableLength), '-');

        if ($truncatedBase === '') {
            $truncatedBase = Str::substr('producto', 0, $availableLength);
        }

        return $truncatedBase.$suffix;
    }
}
