<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->foreignId('shipping_address_id')->nullable()->after('customer_id')
                ->constrained('party_addresses')->nullOnDelete();
            $table->foreignId('shipping_city_id')->nullable()->after('shipping_address_id')
                ->constrained('cities')->nullOnDelete();
            $table->foreignId('shipping_zone_id')->nullable()->after('shipping_city_id')
                ->constrained('shipping_zones')->nullOnDelete();
            $table->foreignId('shipping_warehouse_id')->nullable()->after('shipping_zone_id')
                ->constrained('warehouses')->nullOnDelete();
            $table->string('shipping_zone_name', 100)->nullable()->after('shipping_warehouse_id');
            $table->string('shipping_warehouse_name', 150)->nullable()->after('shipping_zone_name');
            $table->decimal('shipping_fee_min', 12, 2)->nullable()->after('shipping_fee');
            $table->decimal('shipping_fee_max', 12, 2)->nullable()->after('shipping_fee_min');
            $table->unsignedSmallInteger('shipping_days')->nullable()->after('shipping_fee_max');
            $table->string('shipping_type', 20)->nullable()->after('shipping_days');
            $table->date('shipping_date')->nullable()->after('shipping_type');
            $table->index(['shipping_city_id', 'shipping_zone_id']);
            $table->index(['shipping_warehouse_id', 'shipping_date']);
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropIndex(['shipping_city_id', 'shipping_zone_id']);
            $table->dropIndex(['shipping_warehouse_id', 'shipping_date']);
            $table->dropConstrainedForeignId('shipping_warehouse_id');
            $table->dropConstrainedForeignId('shipping_zone_id');
            $table->dropConstrainedForeignId('shipping_city_id');
            $table->dropConstrainedForeignId('shipping_address_id');
            $table->dropColumn([
                'shipping_zone_name', 'shipping_warehouse_name', 'shipping_fee_min',
                'shipping_fee_max', 'shipping_days', 'shipping_type', 'shipping_date',
            ]);
        });
    }
};
