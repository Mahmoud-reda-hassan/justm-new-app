<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->string('document_no', 40)->unique();
            $table->string('movement_type', 30)->index();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('destination_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->string('status', 20)->default('posted')->index();
            $table->dateTime('movement_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('posted_at')->nullable();
            $table->timestamps();
            $table->index(['warehouse_id', 'movement_type', 'movement_date'], 'inventory_movement_warehouse_type_date');
        });

        Schema::create('inventory_movement_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_movement_id')->constrained('inventory_movements')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('quantity_before', 18, 3)->default(0);
            $table->decimal('quantity_after', 18, 3)->default(0);
            $table->timestamps();
            $table->unique(['inventory_movement_id', 'product_variant_id'], 'inventory_movement_variant_unique');
            $table->index(['product_variant_id', 'inventory_movement_id'], 'inventory_movement_item_variant_lookup');
        });

        $permissions = [
            ['key' => 'inventory.opening_balances.view', 'module' => 'inventory', 'action' => 'opening_balances_view', 'description' => 'عرض الأرصدة الافتتاحية'],
            ['key' => 'inventory.opening_balances.create', 'module' => 'inventory', 'action' => 'opening_balances_create', 'description' => 'تسجيل الأرصدة الافتتاحية'],
            ['key' => 'inventory.movements.view', 'module' => 'inventory', 'action' => 'movements_view', 'description' => 'عرض حركات المخزون'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $permission['key']],
                $permission + ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        $keys = [
            'inventory.opening_balances.view',
            'inventory.opening_balances.create',
            'inventory.movements.view',
        ];
        $ids = DB::table('permissions')->whereIn('key', $keys)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        Schema::dropIfExists('inventory_movement_items');
        Schema::dropIfExists('inventory_movements');
    }
};
