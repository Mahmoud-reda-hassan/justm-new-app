<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('allow_preorder')->default(false)->after('is_visible')->index();
        });

        Schema::create('warehouse_product_stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->decimal('quantity_on_hand', 18, 3)->default(0);
            $table->timestamps();
            $table->unique(['warehouse_id', 'product_variant_id'], 'warehouse_variant_stock_unique');
            $table->index(['product_variant_id', 'warehouse_id']);
        });

        Schema::create('sales_order_item_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_order_item_id')->constrained('sales_order_items')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->decimal('requested_quantity', 18, 3);
            $table->decimal('reserved_quantity', 18, 3)->default(0);
            $table->decimal('backorder_quantity', 18, 3)->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique('sales_order_item_id', 'sales_item_reservation_unique');
            $table->index(['warehouse_id', 'product_variant_id', 'status'], 'reservation_stock_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_item_reservations');
        Schema::dropIfExists('warehouse_product_stocks');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['allow_preorder']);
            $table->dropColumn('allow_preorder');
        });
    }
};
