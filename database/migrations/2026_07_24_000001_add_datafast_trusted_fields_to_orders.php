<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('customer_identification', 30)->nullable()->after('customer_phone');
            $table->string('billing_province')->nullable()->after('address');
            $table->string('billing_city')->nullable()->after('billing_province');
            $table->string('billing_address')->nullable()->after('billing_city');
            $table->decimal('tax_base_zero', 10, 2)->nullable()->after('shipping_cost');
            $table->decimal('tax_base_taxable', 10, 2)->nullable()->after('tax_base_zero');
            $table->decimal('tax_amount', 10, 2)->nullable()->after('tax_base_taxable');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'customer_identification',
                'billing_province',
                'billing_city',
                'billing_address',
                'tax_base_zero',
                'tax_base_taxable',
                'tax_amount',
            ]);
        });
    }
};
