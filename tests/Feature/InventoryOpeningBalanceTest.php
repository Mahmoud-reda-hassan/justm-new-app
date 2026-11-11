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
use App\Models\UserDataScope;
use App\Models\Warehouse;
use App\Models\WarehouseType;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
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

    public function test_inventory_movement_supports_source_and_reversal_links(): void
    {
        $original = InventoryMovement::query()->create([
            'document_no' => 'TR-TRACE-001',
            'movement_type' => 'transfer',
            'source_type' => 'sales_order',
            'source_id' => 123,
            'idempotency_key' => 'traceability-test-key',
            'idempotency_hash' => hash('sha256', 'traceability-test'),
            'warehouse_id' => $this->warehouse->id,
            'status' => 'posted',
            'movement_date' => now(),
            'created_by' => $this->user->id,
            'posted_by' => $this->user->id,
            'posted_at' => now(),
        ]);

        $reversal = InventoryMovement::query()->create([
            'document_no' => 'TR-TRACE-002',
            'movement_type' => 'reversal',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'posted',
            'movement_date' => now(),
            'created_by' => $this->user->id,
            'posted_by' => $this->user->id,
            'posted_at' => now(),
            'reversal_of_id' => $original->id,
        ]);

        $this->assertSame($original->id, $reversal->reversalOf->id);
        $this->assertSame($reversal->id, $original->fresh()->reversal->id);
        $this->assertDatabaseHas('inventory_movements', [
            'id' => $original->id,
            'source_type' => 'sales_order',
            'source_id' => 123,
            'idempotency_key' => 'traceability-test-key',
        ]);
    }

    public function test_inventory_movement_idempotency_key_cannot_be_reused(): void
    {
        InventoryMovement::query()->create([
            'document_no' => 'TR-IDEMP-001',
            'movement_type' => 'transfer',
            'idempotency_key' => 'same-operation-key',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'posted',
            'movement_date' => now(),
            'created_by' => $this->user->id,
        ]);

        $this->expectException(QueryException::class);

        InventoryMovement::query()->create([
            'document_no' => 'TR-IDEMP-002',
            'movement_type' => 'transfer',
            'idempotency_key' => 'same-operation-key',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'posted',
            'movement_date' => now(),
            'created_by' => $this->user->id,
        ]);
    }

    public function test_an_inventory_movement_can_be_reversed_only_once(): void
    {
        $original = InventoryMovement::query()->create([
            'document_no' => 'TR-REV-001',
            'movement_type' => 'transfer',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'posted',
            'movement_date' => now(),
            'created_by' => $this->user->id,
        ]);

        InventoryMovement::query()->create([
            'document_no' => 'TR-REV-002',
            'movement_type' => 'reversal',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'posted',
            'movement_date' => now(),
            'created_by' => $this->user->id,
            'reversal_of_id' => $original->id,
        ]);

        $this->expectException(QueryException::class);

        InventoryMovement::query()->create([
            'document_no' => 'TR-REV-003',
            'movement_type' => 'reversal',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'posted',
            'movement_date' => now(),
            'created_by' => $this->user->id,
            'reversal_of_id' => $original->id,
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

    public function test_opening_balance_cannot_be_posted_twice_for_the_same_variant_and_warehouse(): void
    {
        $payload = [
            'warehouse_id' => $this->warehouse->id,
            'items' => [[
                'product_variant_id' => $this->variant->id,
                'quantity' => 12,
            ]],
        ];

        $this->actingAs($this->user)->post(route('admin.inventory.opening-balances.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->actingAs($this->user)->from(route('admin.inventory.opening-balances'))
            ->post(route('admin.inventory.opening-balances.store'), $payload)
            ->assertRedirect(route('admin.inventory.opening-balances'))
            ->assertSessionHasErrors('items');

        $this->assertSame(1, InventoryMovement::query()->where('movement_type', 'opening_balance')->count());
        $this->assertDatabaseHas('warehouse_product_stocks', [
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity_on_hand' => '12.000',
        ]);
    }

    public function test_same_variant_can_have_an_opening_balance_in_a_different_warehouse(): void
    {
        $otherWarehouse = Warehouse::query()->create([
            'branch_id' => $this->warehouse->branch_id,
            'warehouse_type_id' => $this->warehouse->warehouse_type_id,
            'name' => 'مخزن فرعي',
            'is_active' => true,
        ]);

        foreach ([$this->warehouse, $otherWarehouse] as $warehouse) {
            $this->actingAs($this->user)->post(route('admin.inventory.opening-balances.store'), [
                'warehouse_id' => $warehouse->id,
                'items' => [[
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 5,
                ]],
            ])->assertSessionHasNoErrors();
        }

        $this->assertDatabaseHas('warehouse_product_stocks', [
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity_on_hand' => '5.000',
        ]);
        $this->assertDatabaseHas('warehouse_product_stocks', [
            'warehouse_id' => $otherWarehouse->id,
            'product_variant_id' => $this->variant->id,
            'quantity_on_hand' => '5.000',
        ]);
    }

    public function test_user_cannot_post_opening_balance_outside_assigned_warehouse_scope(): void
    {
        $otherWarehouse = Warehouse::query()->create([
            'branch_id' => $this->warehouse->branch_id,
            'warehouse_type_id' => $this->warehouse->warehouse_type_id,
            'name' => 'مخزن خارج النطاق',
            'is_active' => true,
        ]);

        $role = $this->user->roles()->firstOrFail();
        $manageAllPermissionId = Permission::query()->where('key', 'structure.scope.manage_all')->value('id');
        $role->permissions()->detach($manageAllPermissionId);

        UserDataScope::query()->create([
            'user_id' => $this->user->id,
            'scope_type' => 'warehouse',
            'scope_id' => $this->warehouse->id,
        ]);

        $this->actingAs($this->user)
            ->post(route('admin.inventory.opening-balances.store'), [
                'warehouse_id' => $otherWarehouse->id,
                'items' => [[
                    'product_variant_id' => $this->variant->id,
                    'quantity' => 5,
                ]],
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('warehouse_product_stocks', 0);
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
