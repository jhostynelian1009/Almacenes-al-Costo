<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const MAX_LENGTH = 255;

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('slug', self::MAX_LENGTH)->nullable();
        });

        $usedSlugs = [];

        DB::table('products')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->chunk(500, function ($products) use (&$usedSlugs): void {
                foreach ($products as $product) {
                    $slug = $this->uniqueSlug((string) $product->name, $usedSlugs);

                    DB::table('products')
                        ->where('id', $product->id)
                        ->update(['slug' => $slug]);
                }
            });

        Schema::table('products', function (Blueprint $table) {
            $table->string('slug', self::MAX_LENGTH)->nullable(false)->change();
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }

    /**
     * @param  array<string, true>  $usedSlugs
     */
    private function uniqueSlug(string $name, array &$usedSlugs): string
    {
        $base = Str::slug(trim($name));

        if ($base === '') {
            $base = 'producto';
        }

        $base = rtrim(Str::substr($base, 0, self::MAX_LENGTH), '-');
        $candidate = $base;
        $suffix = 1;

        while (isset($usedSlugs[$candidate])) {
            $suffix++;
            $candidate = $this->withSuffix($base, $suffix);
        }

        $usedSlugs[$candidate] = true;

        return $candidate;
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
};
