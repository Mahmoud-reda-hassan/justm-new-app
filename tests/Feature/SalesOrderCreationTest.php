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
use Illuminate\Support\Facades\DB;
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
            ->whereIn('key', ['sales.orders.view', 'sales.orders.create', 'parties.create', 'parties.update', 'structure.scope.manage_all', 'inventory.virtual_stock.view'])
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

    private function setWarehouseStock(\App\Models\PartyAddress $address, ProductVariant $variant, float $quantity): void
    {
        $address->loadMissing('city.shippingZone');
        DB::table('warehouse_product_stocks')->insert([
            'warehouse_id' => $address->city->shippingZone->warehouse_id,
            'product_variant_id' => $variant->id,
            'quantity_on_hand' => $quantity,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_order_is_saved_as_draft_with_price_discount_and_shipping_snapshots(): void
    {
        $user = $this->userWithSalesPermission();
        $customer = $this->customer();
        $variant = $this->variant();
        $shippingAddress = $this->shippingAddress($customer);
        $this->setWarehouseStock($shippingAddress, $variant, 10);
        $phone = $customer->phones()->create([
            'number' => '01012345678',
            'type' => 'mobile',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('admin.sales.orders.store'), [
            'customer_id' => $customer->id,
            'shipping_phone_id' => $phone->id,
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
        $this->assertSame($phone->id, $order->shipping_phone_id);
        $this->assertSame('01012345678', $order->shipping_phone);
        $this->assertSame($shippingAddress->city_id, $order->shipping_city_id);
        $this->assertNotNull($order->shipping_zone_id);
        $this->assertNotNull($order->shipping_warehouse_id);
        $this->assertSame('مخزن البيع', mb_substr($order->shipping_warehouse_name, 0, mb_strlen('مخزن البيع')));
        $this->assertSame(2, $order->shipping_days);
        $this->assertSame(now()->addDays(2)->toDateString(), $order->shipping_date->toDateString());
    }

    public function test_order_can_use_a_later_shipping_date_but_not_earlier_than_zone_delivery_window(): void
    {
        $user = $this->userWithSalesPermission();
        $customer = $this->customer();
        $variant = $this->variant();
        $shippingAddress = $this->shippingAddress($customer);
        $this->setWarehouseStock($shippingAddress, $variant, 10);
        $phone = $customer->phones()->create([
            'number' => '01012345678',
            'type' => 'mobile',
            'is_primary' => true,
            'is_active' => true,
        ]);
        $chosenDate = now()->addDays(5)->toDateString();

        $this->actingAs($user)->post(route('admin.sales.orders.store'), [
            'customer_id' => $customer->id,
            'shipping_phone_id' => $phone->id,
            'shipping_address_id' => $shippingAddress->id,
            'shipping_address' => $shippingAddress->address,
            'shipping_fee' => 10,
            'shipping_date' => $chosenDate,
            'items' => [[
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'sale_price' => 90,
            ]],
        ])->assertRedirect(route('admin.sales.orders.create'));

        $this->assertSame($chosenDate, SalesOrder::query()->firstOrFail()->shipping_date->toDateString());
    }

    public function test_order_rejects_shipping_date_before_the_zone_delivery_window(): void
    {
        $user = $this->userWithSalesPermission();
        $customer = $this->customer();
        $variant = $this->variant();
        $shippingAddress = $this->shippingAddress($customer);
        $phone = $customer->phones()->create([
            'number' => '01012345678',
            'type' => 'mobile',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('admin.sales.orders.store'), [
            'customer_id' => $customer->id,
            'shipping_phone_id' => $phone->id,
            'shipping_address_id' => $shippingAddress->id,
            'shipping_address' => $shippingAddress->address,
            'shipping_fee' => 10,
            'shipping_date' => now()->toDateString(),
            'items' => [[
                'product_variant_id' => $variant->id,
                'quantity' => 1,
                'sale_price' => 90,
            ]],
        ])->assertSessionHasErrors('shipping_date');

        $this->assertDatabaseCount('sales_orders', 0);
    }

    public function test_order_rejects_quantity_above_available_when_preorder_is_disabled(): void
    {
        $user = $this->userWithSalesPermission();
        $customer = $this->customer();
        $variant = $this->variant();
        $shippingAddress = $this->shippingAddress($customer);
        $this->setWarehouseStock($shippingAddress, $variant, 1);
        $phone = $customer->phones()->create([
            'number' => '01012345678',
            'type' => 'mobile',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('admin.sales.orders.store'), [
            'customer_id' => $customer->id,
            'shipping_phone_id' => $phone->id,
            'shipping_address_id' => $shippingAddress->id,
            'shipping_address' => $shippingAddress->address,
            'shipping_fee' => 10,
            'items' => [[
                'product_variant_id' => $variant->id,
                'quantity' => 2,
                'sale_price' => 90,
            ]],
        ])->assertSessionHasErrors('items');

        $this->assertDatabaseCount('sales_orders', 0);
        $this->assertDatabaseCount('sales_order_item_reservations', 0);
    }

    public function test_preorder_product_reserves_available_quantity_and_tracks_the_shortage(): void
    {
        $user = $this->userWithSalesPermission();
        $customer = $this->customer();
        $variant = $this->variant();
        $variant->product->update(['allow_preorder' => true]);
        $shippingAddress = $this->shippingAddress($customer);
        $this->setWarehouseStock($shippingAddress, $variant, 1);
        $phone = $customer->phones()->create([
            'number' => '01012345678',
            'type' => 'mobile',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('admin.sales.orders.store'), [
            'customer_id' => $customer->id,
            'shipping_phone_id' => $phone->id,
            'shipping_address_id' => $shippingAddress->id,
            'shipping_address' => $shippingAddress->address,
            'shipping_fee' => 10,
            'items' => [[
                'product_variant_id' => $variant->id,
                'quantity' => 3,
                'sale_price' => 90,
            ]],
        ])->assertRedirect(route('admin.sales.orders.create'));

        $this->assertDatabaseHas('sales_order_item_reservations', [
            'warehouse_id' => $shippingAddress->city->shippingZone->warehouse_id,
            'product_variant_id' => $variant->id,
            'requested_quantity' => '3.000',
            'reserved_quantity' => '1.000',
            'backorder_quantity' => '2.000',
            'status' => 'active',
        ]);
    }

    public function test_virtual_stock_screen_subtracts_active_order_reservations_from_real_stock(): void
    {
        $user = $this->userWithSalesPermission();
        $customer = $this->customer();
        $variant = $this->variant();
        $shippingAddress = $this->shippingAddress($customer);
        $this->setWarehouseStock($shippingAddress, $variant, 10);
        $phone = $customer->phones()->create([
            'number' => '01012345678',
            'type' => 'mobile',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('admin.sales.orders.store'), [
            'customer_id' => $customer->id,
            'shipping_phone_id' => $phone->id,
            'shipping_address_id' => $shippingAddress->id,
            'shipping_address' => $shippingAddress->address,
            'shipping_fee' => 10,
            'items' => [[
                'product_variant_id' => $variant->id,
                'quantity' => 2,
                'sale_price' => 90,
            ]],
        ])->assertRedirect(route('admin.sales.orders.create'));

        $warehouseId = $shippingAddress->city->shippingZone->warehouse_id;

        $this->actingAs($user)
            ->get(route('admin.inventory.virtual-stock', [
                'type' => 'create_order',
                'warehouse_id' => $warehouseId,
            ]))
            ->assertOk()
            ->assertSee('10.000')
            ->assertSee('2.000')
            ->assertSee('8.000');
    }

    public function test_order_creation_screen_exposes_shipping_date_adjustment(): void
    {
        $user = $this->userWithSalesPermission();

        $this->actingAs($user)->get(route('admin.sales.orders.create'))
            ->assertOk()
            ->assertSee('name="shipping_date"', false)
            ->assertSee('موعد الشحن المتوقع');
    }

    public function test_order_creation_resolves_the_selected_customer_address_from_its_own_address_list(): void
    {
        $user = $this->userWithSalesPermission();

        $this->actingAs($user)->get(route('admin.sales.orders.create'))
            ->assertOk()
            ->assertSee("const addresses = customerAddresses[$('customer_id').value] || [];", false)
            ->assertSee("const address = addresses.find(item => String(item.id) === this.value);", false);
    }

    public function test_order_creation_screen_uses_phone_only_customer_directory_cards(): void
    {
        $user = $this->userWithSalesPermission();
        $customer = $this->customer();
        $customer->phones()->create([
            'number' => '01012345678',
            'type' => 'mobile',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('admin.sales.orders.create'))
            ->assertOk()
            ->assertSee('id="customer-search"', false)
            ->assertSee('بحث برقم هاتف العميل فقط')
            ->assertDontSee('id="top-customer-cards"', false)
            ->assertSee('id="customer-directory-cards"', false)
            ->assertSee('id="customer-directory-pagination"', false)
            ->assertSee('كل العملاء — مرتبين حسب عدد الطلبات المسلّمة')
            ->assertSee('الأكثر تسليمًا')
            ->assertSee('تم التسليم')
            ->assertSee('قيد التجهيز')
            ->assertSee('قيد التأكيد')
            ->assertSee('id="customer-phone-select"', false)
            ->assertSee('إنشاء أمر البيع')
            ->assertSee('id="shipping_phone_id"', false);
    }

    public function test_customer_history_endpoint_returns_only_the_selected_customers_recent_orders(): void
    {
        $user = $this->userWithSalesPermission();
        $customer = $this->customer();
        $otherCustomer = $this->customer();

        SalesOrder::query()->create([
            'order_number' => 'SO-HISTORY-001',
            'customer_id' => $customer->id,
            'created_by' => $user->id,
            'status' => 'draft',
            'subtotal' => 100,
            'discount_total' => 0,
            'total' => 110,
        ]);
        SalesOrder::query()->create([
            'order_number' => 'SO-OTHER-CUSTOMER',
            'customer_id' => $otherCustomer->id,
            'created_by' => $user->id,
            'status' => 'draft',
            'subtotal' => 200,
            'discount_total' => 0,
            'total' => 200,
        ]);

        $this->actingAs($user)
            ->getJson(route('admin.sales.orders.customer-history', $customer))
            ->assertOk()
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.order_number', 'SO-HISTORY-001')
            ->assertJsonPath('orders.0.total', '110.00')
            ->assertJsonMissing(['order_number' => 'SO-OTHER-CUSTOMER']);
    }

    public function test_customer_can_be_created_from_order_wizard_with_primary_phone_and_shipping_address(): void
    {
        $user = $this->userWithSalesPermission();
        $city = City::query()->where('is_active', true)->firstOrFail();

        $response = $this->actingAs($user)->postJson(route('admin.parties.quick-customer.store'), [
            'entity_type' => 'individual',
            'name' => 'عميل من شاشة الطلب',
            'email' => 'quick-customer@example.test',
            'phones' => [[
                'number' => '01055554444',
                'type' => 'mobile',
                'is_primary' => true,
            ]],
            'addresses' => [[
                'city_id' => $city->id,
                'title' => 'عنوان العميل',
                'address' => 'شارع العميل الجديد',
                'is_primary' => true,
            ]],
        ]);

        $response->assertCreated();
        $customer = Party::query()->where('name', 'عميل من شاشة الطلب')->firstOrFail();
        $this->assertSame(['customer'], $customer->assignedRoles());
        $this->assertSame('01055554444', $customer->mobile);
        $this->assertSame('شارع العميل الجديد', $customer->address);
        $this->assertDatabaseHas('party_phones', [
            'party_id' => $customer->id,
            'number' => '01055554444',
            'is_primary' => true,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('party_addresses', [
            'party_id' => $customer->id,
            'city_id' => $city->id,
            'address' => 'شارع العميل الجديد',
            'is_primary' => true,
            'is_active' => true,
        ]);
        $response->assertJsonPath('customer.id', $customer->id);
    }

    public function test_customer_can_be_edited_without_removing_secondary_addresses(): void
    {
        $user = $this->userWithSalesPermission();
        $customer = $this->customer();
        $city = City::query()->where('is_active', true)->firstOrFail();
        $customer->phones()->create([
            'number' => '01011112222',
            'type' => 'mobile',
            'is_primary' => true,
            'is_active' => true,
        ]);
        $primary = $customer->addresses()->create([
            'city_id' => $city->id,
            'title' => 'العنوان الرئيسي',
            'address' => 'العنوان القديم',
            'is_primary' => true,
            'is_active' => true,
        ]);
        $secondary = $customer->addresses()->create([
            'city_id' => $city->id,
            'title' => 'عنوان إضافي',
            'address' => 'عنوان إضافي محفوظ',
            'is_primary' => false,
            'is_active' => true,
        ]);

        $this->actingAs($user)->putJson(route('admin.parties.quick-customer.update', $customer), [
            'entity_type' => 'company',
            'name' => 'اسم العميل بعد التعديل',
            'email' => 'updated-customer@example.test',
            'phones' => [[
                'id' => $customer->phones()->firstOrFail()->id,
                'number' => '01099998888',
                'type' => 'mobile',
                'is_primary' => true,
            ]],
            'addresses' => [
                [
                    'id' => $primary->id,
                    'city_id' => $city->id,
                    'title' => 'العنوان الرئيسي',
                    'address' => 'العنوان بعد التعديل',
                    'is_primary' => true,
                ],
                [
                    'id' => $secondary->id,
                    'city_id' => $city->id,
                    'title' => 'عنوان إضافي',
                    'address' => 'عنوان إضافي محفوظ',
                    'is_primary' => false,
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('customer.name', 'اسم العميل بعد التعديل')
            ->assertJsonPath('customer.addresses.0.address', 'العنوان بعد التعديل');

        $this->assertSame('01099998888', $customer->fresh()->mobile);
        $this->assertSame('company', $customer->fresh()->entity_type);
        $this->assertSame('العنوان بعد التعديل', $primary->fresh()->address);
        $this->assertTrue($primary->fresh()->is_primary);
        $this->assertTrue($secondary->fresh()->is_active);
        $this->assertFalse($secondary->fresh()->is_primary);
        $this->assertSame(1, $customer->phones()->where('is_primary', true)->count());
        $this->assertSame(1, $customer->addresses()->where('is_primary', true)->count());
        $this->assertSame(['customer'], $customer->fresh()->assignedRoles());
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

        $this->actingAs($user)
            ->getJson(route('admin.sales.orders.customers.selection', $customer))
            ->assertOk()
            ->assertJsonPath('customer.addresses.0.address', 'شارع الاختبار، عمارة 10')
            ->assertJsonPath('customer.addresses.0.title', 'عنوان العمل')
            ->assertJsonPath('customer.addresses.0.isPrimary', true);
    }

    public function test_order_rejects_a_phone_belonging_to_another_customer(): void
    {
        $user = $this->userWithSalesPermission();
        $customer = $this->customer();
        $otherCustomer = $this->customer();
        $variant = $this->variant();
        $shippingAddress = $this->shippingAddress($customer);
        $otherPhone = $otherCustomer->phones()->create([
            'number' => '01077776666',
            'type' => 'mobile',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)->from(route('admin.sales.orders.create'))
            ->post(route('admin.sales.orders.store'), [
                'customer_id' => $customer->id,
                'shipping_phone_id' => $otherPhone->id,
                'shipping_address_id' => $shippingAddress->id,
                'shipping_address' => $shippingAddress->address,
                'shipping_fee' => 10,
                'items' => [[
                    'product_variant_id' => $variant->id,
                    'quantity' => 1,
                    'sale_price' => 90,
                ]],
            ])->assertSessionHasErrors('shipping_phone_id');

        $this->assertDatabaseCount('sales_orders', 0);
    }

    public function test_order_rejects_sale_price_below_variant_minimum_and_saves_nothing(): void
    {
        $user = $this->userWithSalesPermission();
        $customer = $this->customer();
        $variant = $this->variant();
        $shippingAddress = $this->shippingAddress($customer);
        $phone = $customer->phones()->create([
            'number' => '01011112222',
            'type' => 'mobile',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)->from(route('admin.sales.orders.create'))
            ->post(route('admin.sales.orders.store'), [
                'customer_id' => $customer->id,
                'shipping_phone_id' => $phone->id,
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
            ->assertSee('اختر رقم هاتف نشطًا للعميل.')
            ->assertSee('اختر عنوانًا مرتبطًا بمنطقة شحن ومخزن نشطين')
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
    public function test_order_creation_uses_legacy_visual_system_and_three_step_navigation(): void
    {
        $user = $this->userWithSalesPermission();

        $this->actingAs($user)->get(route('admin.sales.orders.create'))
            ->assertOk()
            ->assertSee('css/order-storefront-legacy.css')
            ->assertSee('class="jm-order-steps"', false)
            ->assertSee('data-order-step="step-customer"', false)
            ->assertSee('data-order-step="step-products"', false)
            ->assertSee('data-order-step="step-review"', false)
            ->assertSee('class="jm-order-storefront-page"', false);
    }


    public function test_order_catalog_availability_uses_virtual_stock_status_policy(): void
    {
        $user = $this->userWithSalesPermission();
        $customer = $this->customer();
        $variant = $this->variant();
        $address = $this->shippingAddress($customer);
        $this->setWarehouseStock($address, $variant, 10);
        $warehouseId = (int) $address->city->shippingZone->warehouse_id;

        foreach ([
            ['status' => 'draft', 'quantity' => 2, 'number' => 'SO-VIRTUAL-DRAFT'],
            ['status' => 'cancelled', 'quantity' => 3, 'number' => 'SO-VIRTUAL-CANCELLED'],
        ] as $row) {
            $order = SalesOrder::query()->create([
                'order_number' => $row['number'],
                'customer_id' => $customer->id,
                'created_by' => $user->id,
                'status' => $row['status'],
                'subtotal' => 0,
                'discount_total' => 0,
                'total' => 0,
            ]);
            $item = $order->items()->create([
                'product_variant_id' => $variant->id,
                'product_name' => 'منتج مبيعات',
                'variant_sku' => $variant->sku,
                'quantity' => $row['quantity'],
                'list_price' => 100,
                'sale_price' => 100,
                'discount_amount' => 0,
                'line_total' => $row['quantity'] * 100,
            ]);
            DB::table('sales_order_item_reservations')->insert([
                'sales_order_item_id' => $item->id,
                'warehouse_id' => $warehouseId,
                'product_variant_id' => $variant->id,
                'requested_quantity' => $row['quantity'],
                'reserved_quantity' => $row['quantity'],
                'backorder_quantity' => 0,
                'status' => 'active',
                'created_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($user)
            ->getJson(route('admin.sales.orders.inventory-availability', ['address_id' => $address->id]))
            ->assertOk()
            ->assertJsonPath('variants.'.$variant->id.'.available_quantity', 8);
    }

    public function test_virtual_stock_settings_screen_and_matrix_can_be_saved(): void
    {
        $user = $this->userWithSalesPermission();
        $role = $user->roles()->firstOrFail();
        $role->permissions()->attach(Permission::query()
            ->whereIn('key', ['inventory.settings.view', 'inventory.settings.update'])
            ->pluck('id'));

        $this->actingAs($user)->get(route('admin.inventory.settings'))
            ->assertOk()
            ->assertSee('المخزن الوهمي لمنشئ أمر البيع')
            ->assertSee('المخزن الوهمي لخدمة العملاء')
            ->assertSee('المخزن الوهمي لأمين المخزن والتجهيز')
            ->assertSee('إلغاء من لا يستجيب');

        $this->actingAs($user)->post(route('admin.inventory.settings.virtual-stock.update'), [
            'create_order_statuses' => ['draft', 'preparing'],
            'customer_service_matrix' => [
                'draft' => ['draft', 'prepared'],
                'preparing' => ['prepared'],
            ],
            'warehouse_statuses' => ['prepared'],
        ])->assertRedirect(route('admin.inventory.settings'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'inventory.virtual_stock.create_order_statuses',
            'setting_value' => '["draft","preparing"]',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'inventory.virtual_stock.warehouse_statuses',
            'setting_value' => '["prepared"]',
        ]);
    }

}
