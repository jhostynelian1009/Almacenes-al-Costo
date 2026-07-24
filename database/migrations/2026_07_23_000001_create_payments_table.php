<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('gateway', 50);
            $table->string('payment_method', 30);
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'failed',
                'expired',
                'refunded',
            ])->default('pending');
            $table->string('transaction_id', 255)->nullable();
            $table->string('gateway_response_code', 50)->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('status');
            $table->index('transaction_id');
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->enum('event_type', [
                'request',
                'response',
                'webhook_received',
                'callback_received',
                'error',
            ]);
            $table->json('payload');
            $table->timestamp('created_at')->nullable();

            $table->index('payment_id');
        });

        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_path', 500);
            $table->string('original_filename', 255)->nullable();
            $table->enum('payment_method', ['transfer', 'deuna']);
            $table->string('transaction_reference', 100)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('uploaded_at')->useCurrent();

            $table->index('order_id');
            $table->index('payment_id');
        });

        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 50);
            $table->string('event_type', 100)->nullable();
            $table->string('event_id', 255)->unique();
            $table->json('payload');
            $table->boolean('signature_verified')->default(false);
            $table->boolean('processed')->default(false);
            $table->text('processing_error')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('gateway');
            $table->index('processed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
        Schema::dropIfExists('payment_receipts');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payments');
    }
};
