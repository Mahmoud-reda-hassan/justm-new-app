<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table): void {
            $table->id();
            $table->string('name_ar', 100);
            $table->string('name_en', 100);
            $table->decimal('shipping_fee_min', 12, 2)->default(0);
            $table->decimal('shipping_fee_max', 12, 2);
            $table->unsignedSmallInteger('delivery_days')->default(1);
            $table->string('shipping_type', 20)->default('internal');
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['warehouse_id', 'name_ar']);
            $table->unique(['warehouse_id', 'name_en']);
            $table->index(['is_active', 'warehouse_id']);
        });

        Schema::table('cities', function (Blueprint $table): void {
            $table->foreignId('shipping_zone_id')->nullable()->after('governorate_id')
                ->constrained('shipping_zones')->nullOnDelete();
            $table->index(['shipping_zone_id', 'is_active']);
        });

        foreach ([
            ['shipping_zones.view', 'shipping', 'view', 'عرض مناطق الشحن المحلي'],
            ['shipping_zones.create', 'shipping', 'create', 'إضافة مناطق الشحن المحلي'],
            ['shipping_zones.update', 'shipping', 'update', 'تعديل مناطق الشحن المحلي'],
            ['shipping_zones.activate', 'shipping', 'activate', 'تفعيل وإيقاف مناطق الشحن المحلي'],
        ] as [$key, $module, $action, $description]) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $key],
                ['module' => $module, 'action' => $action, 'description' => $description, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('key', [
            'shipping_zones.view', 'shipping_zones.create', 'shipping_zones.update', 'shipping_zones.activate',
        ])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::table('cities', function (Blueprint $table): void {
            $table->dropIndex(['shipping_zone_id', 'is_active']);
            $table->dropConstrainedForeignId('shipping_zone_id');
        });
        Schema::dropIfExists('shipping_zones');
    }
};
