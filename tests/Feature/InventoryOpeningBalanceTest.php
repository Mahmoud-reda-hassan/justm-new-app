<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InventoryMovement;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseType;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryOpeningBalanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Warehouse $warehouse;
    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        app(DatabaseSeeder::class)->run();

        $this->user = User::query()->create([
            'name' => 'Inventory Admin',
            'username' => 'inventory-admin-'.uniqid(),
            'password' => 'secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);

        $role = Role::query()->create([
            'name' => 'Inventory role',
            'key' => 'inventory-role-'.uniqid(),
            'is_active' => true,
        ]);
        $role->permissions()->sync(Permission::query()->whereIn('key', [
            'inventory.opening_balances.view',
            'inventory.opening_balances.create',
            'structure.scope.manage_all',
        ])->pluck('id'));
        $this->user->roles()->attach($role);

        $company = Company::query()->create(['name' => 'شركة المخزون']);
        $branch = $company->branches()->create(['name' => 'الفرع الرئيسي', 'is_main' => true, 'is_active' => true]);
        $type = WarehouseType::query()->where('key', 'internal')->firstOrFail();
        $this->warehouse = Warehouse::query()->create([
            'branch_id' => $branch->id,
            'warehouse_type_id' => $type->id,
            'name' => 'المخزن الرئيسي',
            'is_active' => true,
        ]);

        $category = ProductCategory::query()->create(['name' => 'ملابس']);
        $unit = Unit::query()->create(['name' => 'قطعة', 'symbol' => 'قطعة', 'decimal_places' => 0]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'قميص تجريبي',
            'is_visible' => true,
            'is_active' => true,
        ]);
        $this->variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'SHIRT-001',
            'variant_name' => 'أسود / L',
            'list_price' => 300,
            'minimum_sale_price' => 250,
            'is_active' => true,
        ]);
    }

    public function test_opening_balance_screen_shows_active_warehouse_and_variant(): void
    {
        $this->actingAs($this->user)
            ->get(route('admin.inventory.opening-balances'))
            ->assertOk()
            ->assertSee('الأرصدة الافتتاحية')
            ->assertSee('المخزن الرئيسي')
            ->assertSee('قميص تجريبي')
            ->assertSee('حفظ وترحيل الرصيد الافتتاحي');
    }

    public function test_opening_balance_posts_a_document_and_increases_stock_from_zero(): void
    {
        $this->actingAs($this->user)->post(route('admin.inventory.opening-balances.store'), [
            'warehouse_id' => $this->warehouse->id,
            'notes' => 'جرد بداية التشغيل',
            'items' => [[
                'product_variant_id' => $this->variant->id,
                'quantity' => '12.500',
                'unit_cost' => '75.2500',
            ]],
        ])->assertRedirect(route('admin.inventory.opening-balances'))
          ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('warehouse_product_stocks', [
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity_on_hand' => '12.500',
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => 'opening_balance',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'posted',
            'created_by' => $this->user->id,
            'posted_by' => $this->user->id,
        ]);
        $this->assertDatabaseHas('inventory_movement_items', [
            'product_variant_id' => $this->variant->id,
            'quantity' => '12.500',
            'quantity_before' => '0.000',
            'quantity_after' => '12.500',
            'unit_cost' => '75.2500',
        ]);
        $this->assertSame(1, InventoryMovement::query()->where('movement_type', 'opening_balance')->count());
    }

    public function test_opening_balance_rejects_zero_quantity_without_creating_a_document(): void
    {
        $this->actingAs($this->user)->from(route('admin.inventory.opening-balances'))
            ->post(route('admin.inventory.opening-balances.store'), [
                'warehouse_id' => $this->warehouse->id,
                'items' => [[
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 0,
                ]],
            ])
            ->assertRedirect(route('admin.inventory.opening-balances'))
            ->assertSessionHasErrors('items.0.quantity');

        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('warehouse_product_stocks', 0);
    }

    public function test_opening_balance_rejects_duplicate_variant_rows(): void
    {
        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                ['product_variant_id' => $this->variant->id, 'quantity' => 2],
                ['product_variant_id' => $this->variant->id, 'quantity' => 3],
            ],
        ];

        $this->actingAs($this->user)->post(route('admin.inventory.opening-balances.store'), $payload)
            ->assertSessionHasErrors('items.1.product_variant_id');

        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('warehouse_product_stocks', 0);
    }
}
