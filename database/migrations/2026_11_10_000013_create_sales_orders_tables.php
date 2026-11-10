<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // This migration runs after product variants exist. The guards also
        // recover safely when a previous failed attempt created sales_orders
        // before failing while creating sales_order_items.
        if (! Schema::hasTable('sales_orders')) {
            Schema::create('sales_orders', function (Blueprint $table): void {
                $table->id();
                $table->string('order_number', 32)->nullable()->unique();
                $table->foreignId('customer_id')->constrained('parties');
                $table->foreignId('created_by')->constrained('users');
                $table->foreignId('created_by_party_id')->nullable()->constrained('parties')->nullOnDelete();
                $table->string('status', 24)->default('draft')->index();
                $table->text('shipping_address')->nullable();
                $table->decimal('shipping_fee', 12, 2)->default(0);
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('discount_total', 14, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['customer_id', 'created_at']);
                $table->index(['created_by_party_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('sales_order_items')) {
            Schema::create('sales_order_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
                $table->foreignId('product_variant_id')->constrained('product_variants');
                $table->string('product_name', 180);
                $table->string('variant_sku', 50);
                $table->decimal('quantity', 12, 3);
                $table->decimal('list_price', 12, 2);
                $table->decimal('sale_price', 12, 2);
                $table->decimal('discount_amount', 12, 2);
                $table->decimal('line_total', 14, 2);
                $table->timestamps();
                $table->index(['product_variant_id', 'sales_order_id']);
            });
        }

        DB::table('permissions')->updateOrInsert(
            ['key' => 'sales.orders.view'],
            ['module' => 'sales', 'action' => 'view', 'description' => 'عرض أوامر البيع', 'created_at' => now(), 'updated_at' => now()],
        );
        DB::table('permissions')->updateOrInsert(
            ['key' => 'sales.orders.create'],
            ['module' => 'sales', 'action' => 'create', 'description' => 'إنشاء أوامر البيع وحفظها كمسودات', 'created_at' => now(), 'updated_at' => now()],
        );
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('key', ['sales.orders.view', 'sales.orders.create'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_orders');
    }
};
