<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('product_id')->unique();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('reserved_stock')->default(0);
            $table->unsignedInteger('min_stock')->default(0);
            $table->timestamp('updated_at')->nullable();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->restrictOnDelete();
        });

        DB::table('products')
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($products): void {
                $timestamp = now();
                $inventories = $products->map(fn ($product): array => [
                    'product_id' => $product->id,
                    'stock' => 0,
                    'reserved_stock' => 0,
                    'min_stock' => 0,
                    'updated_at' => $timestamp,
                ])->all();

                if ($inventories !== []) {
                    DB::table('inventories')->insertOrIgnore($inventories);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
