<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_attribute_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('product_attribute_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained('product_attribute_groups')->restrictOnDelete();
            $table->string('value', 120);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['group_id', 'value']);
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('product_categories')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('name', 180)->unique();
            $table->text('description')->nullable();
            $table->string('image_path', 500)->nullable();
            $table->decimal('length_cm', 12, 3)->nullable();
            $table->decimal('width_cm', 12, 3)->nullable();
            $table->decimal('height_cm', 12, 3)->nullable();
            $table->decimal('weight_kg', 12, 3)->nullable();
            $table->boolean('is_visible')->default(true)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('product_product_attribute_group', function (Blueprint $table): void {
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('attribute_group_id')->constrained('product_attribute_groups')->restrictOnDelete();
            $table->primary(['product_id', 'attribute_group_id']);
        });

        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('sku', 50)->unique();
            $table->string('variant_name', 180)->nullable();
            $table->decimal('list_price', 18, 2);
            $table->decimal('minimum_sale_price', 18, 2);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('product_variant_attribute_values', function (Blueprint $table): void {
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained('product_attribute_values')->restrictOnDelete();
            $table->primary(['product_variant_id', 'attribute_value_id']);
        });

        $permissions = [
            ['key' => 'products.view', 'module' => 'products', 'action' => 'view', 'description' => 'مشاهدة المنتجات والأكواد'],
            ['key' => 'products.create', 'module' => 'products', 'action' => 'create', 'description' => 'إنشاء منتج وكود'],
            ['key' => 'products.update', 'module' => 'products', 'action' => 'update', 'description' => 'تعديل بيانات المنتج والأكواد'],
            ['key' => 'products.activate', 'module' => 'products', 'action' => 'activate', 'description' => 'تفعيل أو إيقاف المنتج والكود'],
            ['key' => 'product_attributes.view', 'module' => 'product_attributes', 'action' => 'view', 'description' => 'مشاهدة مجموعات صفات المنتجات'],
            ['key' => 'product_attributes.create', 'module' => 'product_attributes', 'action' => 'create', 'description' => 'إنشاء مجموعة صفة أو قيمة'],
            ['key' => 'product_attributes.update', 'module' => 'product_attributes', 'action' => 'update', 'description' => 'تعديل مجموعة صفة أو قيمة'],
            ['key' => 'product_attributes.activate', 'module' => 'product_attributes', 'action' => 'activate', 'description' => 'تفعيل أو إيقاف مجموعة صفة أو قيمة'],
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
            'products.view', 'products.create', 'products.update', 'products.activate',
            'product_attributes.view', 'product_attributes.create', 'product_attributes.update', 'product_attributes.activate',
        ];
        $ids = DB::table('permissions')->whereIn('key', $keys)->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        Schema::dropIfExists('product_variant_attribute_values');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_product_attribute_group');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_attribute_groups');
    }
};
