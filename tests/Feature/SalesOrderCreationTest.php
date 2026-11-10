<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\City;
use App\Models\Company;
use App\Models\Party;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\ShippingZone;
use App\Models\Warehouse;
use App\Models\WarehouseType;
use App\Services\BranchManager;
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

    private function shippingAddress(Party $customer): \App\Models\PartyAddress
    {
        app(DatabaseSeeder::class)->run();
        $city = City::query()->where('is_active', true)->firstOrFail();
        $company = Company::query()->create(['name' => 'شركة الشحن '.uniqid(), 'is_active' => true]);
        $branch = app(BranchManager::class)->create([
            'company_id' => $company->id,
            'city_id' => $city->id,
            'name' => 'فرع الشحن '.uniqid(),
            'address' => 'عنوان الفرع',
            'is_main' => true,
            'is_active' => true,
        ]);
        $type = WarehouseType::query()->where('key', 'sales_dispatch')->firstOrFail();
        $warehouse = Warehouse::query()->create([
            'branch_id' => $branch->id,
            'warehouse_type_id' => $type->id,
            'name' => 'مخزن البيع '.uniqid(),
            'is_active' => true,
        ]);
        $zone = ShippingZone::query()->create([
            'name_ar' => 'منطقة الاختبار '.uniqid(),
            'name_en' => 'Test zone '.uniqid(),
            'shipping_fee_min' => 5,
            'shipping_fee_max' => 30,
            'delivery_days' => 2,
            'shipping_type' => 'internal',
            'warehouse_id' => $warehouse->id,
            'is_active' => true,
        ]);
        $city->forceFill(['shipping_zone_id' => $zone->id])->save();

        return $customer->addresses()->create([
            'city_id' => $city->id,
            'title' => 'عنوان الشحن',
            'address' => 'شارع الاختبار، عمارة 10',
            'is_primary' => true,
            'is_active' => true,
        ]);
    }

    public function test_order_is_saved_as_draft_with_price_discount_and_shipping_snapshots(): void
    {
        $user = $this->userWithSalesPermission();
        $customer = $this->customer();
        $variant = $this->variant();
        $shippingAddress = $this->shippingAddress($customer);

        $this->actingAs($user)->post(route('admin.sales.orders.store'), [
            'customer_id' => $customer->id,
            'shipping_address_id' => $shippingAddress->id,
            'shipping_address' => $shippingAddress->address,
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
        $this->assertSame($shippingAddress->id, $order->shipping_address_id);
        $this->assertSame($shippingAddress->city_id, $order->shipping_city_id);
        $this->assertNotNull($order->shipping_zone_id);
        $this->assertNotNull($order->shipping_warehouse_id);
        $this->assertSame('مخزن البيع', mb_substr($order->shipping_warehouse_name, 0, mb_strlen('مخزن البيع')));
        $this->assertSame(2, $order->shipping_days);
        $this->assertSame(now()->addDays(2)->toDateString(), $order->shipping_date->toDateString());
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
        $shippingAddress = $this->shippingAddress($customer);

        $this->actingAs($user)->from(route('admin.sales.orders.create'))
            ->post(route('admin.sales.orders.store'), [
                'customer_id' => $customer->id,
                'shipping_address_id' => $shippingAddress->id,
                'shipping_address' => $shippingAddress->address,
                'shipping_fee' => 10,
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

    public function test_order_list_searches_by_order_number_and_customer(): void
    {
        $user = $this->userWithSalesPermission();
        $customer = $this->customer();
        SalesOrder::query()->create([
            'order_number' => 'SO-SEARCH-2026',
            'customer_id' => $customer->id,
            'created_by' => $user->id,
            'status' => 'draft',
            'subtotal' => 100,
            'discount_total' => 0,
            'total' => 100,
        ]);

        $this->actingAs($user)->get(route('admin.sales.orders.index', ['q' => 'SO-SEARCH-2026']))
            ->assertOk()->assertSee('SO-SEARCH-2026')->assertSee($customer->name);
        $this->get(route('admin.sales.orders.index', ['q' => 'غير موجود']))
            ->assertOk()->assertDontSee('SO-SEARCH-2026');
    }

    public function test_order_screens_require_explicit_sales_permissions(): void
    {
        $user = $this->userWithSalesPermission();
        $this->actingAs($user)->get(route('admin.sales.orders.create'))->assertOk();
        $this->actingAs($user)->get(route('admin.sales.orders.index'))->assertOk();
    }
}
