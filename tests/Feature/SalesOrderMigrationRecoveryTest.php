<?php

namespace Tests\Feature;

use App\Models\Party;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SalesOrderMigrationRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_order_migration_runs_after_product_variants_migration(): void
    {
        $migrationFiles = glob(database_path('migrations/*.php'));

        $productsMigration = collect($migrationFiles)
            ->first(fn (string $file): bool => str_ends_with($file, '_create_products_and_variants.php'));
        $salesMigration = collect($migrationFiles)
            ->first(fn (string $file): bool => str_ends_with($file, '_create_sales_orders_tables.php'));

        $this->assertNotNull($productsMigration);
        $this->assertNotNull($salesMigration);
        $this->assertLessThan(basename($salesMigration), basename($productsMigration));
    }

    public function test_sales_order_migration_recovers_when_only_the_parent_table_exists(): void
    {
        $user = User::query()->create([
            'name' => 'Migration Recovery User',
            'username' => 'migration-recovery-'.uniqid(),
            'password' => 'secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);

        $customer = Party::query()->create([
            'entity_type' => 'company',
            'name' => 'Migration Recovery Customer',
            'is_active' => true,
        ]);

        $orderId = DB::table('sales_orders')->insertGetId([
            'customer_id' => $customer->id,
            'created_by' => $user->id,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::drop('sales_order_items');

        $migration = require database_path('migrations/2026_11_10_000013_create_sales_orders_tables.php');
        $migration->up();

        $this->assertTrue(Schema::hasTable('sales_orders'));
        $this->assertTrue(Schema::hasTable('sales_order_items'));
        $this->assertDatabaseHas('sales_orders', ['id' => $orderId]);

        $category = ProductCategory::query()->create(['name' => 'Migration Recovery Category']);
        $unit = Unit::query()->create([
            'name' => 'Migration Recovery Unit',
            'symbol' => 'MRU',
            'decimal_places' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Migration Recovery Product',
            'is_active' => true,
            'is_visible' => true,
        ]);
        $variant = $product->variants()->create([
            'sku' => 'MIG-'.uniqid(),
            'list_price' => 100,
            'minimum_sale_price' => 70,
            'is_active' => true,
        ]);

        DB::table('sales_order_items')->insert([
            'sales_order_id' => $orderId,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_sku' => $variant->sku,
            'quantity' => 1,
            'list_price' => 100,
            'sale_price' => 90,
            'discount_amount' => 10,
            'line_total' => 90,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('sales_order_items', [
            'sales_order_id' => $orderId,
            'product_variant_id' => $variant->id,
        ]);
    }
}
