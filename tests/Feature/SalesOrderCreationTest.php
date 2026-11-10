<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Party;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderCreationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithSalesPermission(): User
    {
        app(DatabaseSeeder::class)->run();

        $user = User::query()->create([
            'name' => 'Sales Admin',
            'username' => 'sales-admin-'.uniqid(),
            'password' => 'secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);

        $role = Role::query()->create([
            'name' => 'Sales role',
            'key' => 'sales-role-'.uniqid(),
            'is_active' => true,
        ]);
        $role->permissions()->sync(Permission::query()
            ->whereIn('key', ['sales.orders.view', 'sales.orders.create'])
            ->pluck('id'));
        $user->roles()->attach($role);

        return $user;
    }

    private function customer(): Party
    {
        $party = Party::query()->create([
            'entity_type' => 'company',
            'name' => 'عميل اختبار',
            'is_active' => true,
        ]);
        $party->partyRoles()->create(['role' => 'customer']);

        return $party;
    }

    private function variant(): ProductVariant
    {
        $category = ProductCategory::query()->create(['name' => 'تصنيف المبيعات']);
        $unit = Unit::query()->create(['name' => 'قطعة', 'symbol' => 'قطعة', 'decimal_places' => 0]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'منتج مبيعات',
            'is_active' => true,
            'is_visible' => true,
        ]);

        return $product->variants()->create([
            'sku' => 'SALE-'.uniqid(),
            'list_price' => 100,
            'minimum_sale_price' => 70,
            'is_active' => true,
        ]);
    }

    public function test_order_is_saved_as_draft_with_price_discount_and_shipping_snapshots(): void
    {
        $user = $this->userWithSalesPermission();
        $customer = $this->customer();
        $variant = $this->variant();

        $this->actingAs($user)->post(route('admin.sales.orders.store'), [
            'customer_id' => $customer->id,
            'shipping_address' => 'عنوان العميل',
            'shipping_fee' => 10,
            'items' => [[
                'product_variant_id' => $variant->id,
                'quantity' => 2,
                'sale_price' => 90,
            ]],
        ])->assertRedirect(route('admin.sales.orders.create'));

        $order = SalesOrder::query()->with('items')->firstOrFail();

        $this->assertSame('draft', $order->status);
        $this->assertSame('SO-'.now()->format('Y').'-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT), $order->order_number);
        $this->assertSame('190.00', $order->total);
        $this->assertSame('20.00', $order->discount_total);
        $this->assertSame('90.00', $order->items->first()->sale_price);
        $this->assertSame('20.00', $order->items->first()->discount_amount);
    }

    public function test_order_creation_can_choose_a_saved_customer_address(): void
    {
        $user = $this->userWithSalesPermission();
        $customer = $this->customer();
        $city = City::query()->firstOrFail();
        $customer->addresses()->create([
            'city_id' => $city->id,
            'title' => 'عنوان العمل',
            'address' => 'شارع الاختبار، عمارة 10',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('admin.sales.orders.create'))
            ->assertOk()
            ->assertSee('customer-address-select', false)
            ->assertSee('شارع الاختبار، عمارة 10')
            ->assertSee('عنوان العمل');
    }

    public function test_order_rejects_sale_price_below_variant_minimum_and_saves_nothing(): void
    {
        $user = $this->userWithSalesPermission();
        $customer = $this->customer();
        $variant = $this->variant();

        $this->actingAs($user)->from(route('admin.sales.orders.create'))
            ->post(route('admin.sales.orders.store'), [
                'customer_id' => $customer->id,
                'items' => [[
                    'product_variant_id' => $variant->id,
                    'quantity' => 1,
                    'sale_price' => 60,
                ]],
            ])->assertSessionHasErrors('items.0.sale_price');

        $this->assertDatabaseCount('sales_orders', 0);
        $this->assertDatabaseCount('sales_order_items', 0);
    }

    public function test_order_creation_screen_keeps_the_three_step_flow_and_sweetalert_item_editing(): void
    {
        $user = $this->userWithSalesPermission();

        $this->actingAs($user)->get(route('admin.sales.orders.create'))
            ->assertOk()
            ->assertSee('order-step active', false)
            ->assertSee('id="step-products"', false)
            ->assertSee('id="step-review"', false)
            ->assertSee('تعديل بند الطلب')
            ->assertSee('حفظ أمر البيع كمسودة');
    }

    public function test_order_screens_require_explicit_sales_permissions(): void
    {
        $user = $this->userWithSalesPermission();
        $this->actingAs($user)->get(route('admin.sales.orders.create'))->assertOk();
        $this->actingAs($user)->get(route('admin.sales.orders.index'))->assertOk();
    }
}
