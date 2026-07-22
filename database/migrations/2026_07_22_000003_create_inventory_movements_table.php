<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_id');
            $table->string('type', 30);
            $table->integer('stock_delta')->default(0);
            $table->integer('reserved_delta')->default(0);
            $table->unsignedInteger('stock_before');
            $table->unsignedInteger('stock_after');
            $table->unsignedInteger('reserved_before');
            $table->unsignedInteger('reserved_after');
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('inventory_id')
                ->references('id')
                ->on('inventories')
                ->restrictOnDelete();
            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->index(['inventory_id', 'created_at']);
            $table->index('type');
            $table->index('created_by');
            $table->index(['reference_type', 'reference_id']);
            $table->unique('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
