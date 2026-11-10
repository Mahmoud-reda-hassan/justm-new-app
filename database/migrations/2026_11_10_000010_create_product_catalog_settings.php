<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('code', 50)->nullable()->unique();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('symbol', 20)->nullable()->unique();
            $table->unsignedTinyInteger('decimal_places')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $permissions = [
            ['key' => 'product_categories.view', 'module' => 'product_categories', 'action' => 'view', 'description' => 'مشاهدة تصنيفات المنتجات'],
            ['key' => 'product_categories.create', 'module' => 'product_categories', 'action' => 'create', 'description' => 'إنشاء تصنيف منتج'],
            ['key' => 'product_categories.update', 'module' => 'product_categories', 'action' => 'update', 'description' => 'تعديل تصنيف منتج'],
            ['key' => 'product_categories.activate', 'module' => 'product_categories', 'action' => 'activate', 'description' => 'تفعيل أو إيقاف تصنيف منتج'],
            ['key' => 'units.view', 'module' => 'units', 'action' => 'view', 'description' => 'مشاهدة وحدات القياس'],
            ['key' => 'units.create', 'module' => 'units', 'action' => 'create', 'description' => 'إنشاء وحدة قياس'],
            ['key' => 'units.update', 'module' => 'units', 'action' => 'update', 'description' => 'تعديل وحدة قياس'],
            ['key' => 'units.activate', 'module' => 'units', 'action' => 'activate', 'description' => 'تفعيل أو إيقاف وحدة قياس'],
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
            'product_categories.view', 'product_categories.create', 'product_categories.update', 'product_categories.activate',
            'units.view', 'units.create', 'units.update', 'units.activate',
        ];
        $ids = DB::table('permissions')->whereIn('key', $keys)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        Schema::dropIfExists('units');
        Schema::dropIfExists('product_categories');
    }
};
