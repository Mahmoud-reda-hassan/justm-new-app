<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Party;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderCustomerDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function salesUser(): User
    {
        app(DatabaseSeeder::class)->run();

        $user = User::query()->create([
            'name' => 'Sales Directory Admin',
            'username' => 'sales-directory-'.uniqid(),
            'password' => 'secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);

        $role = Role::query()->create([
            'name' => 'Sales Directory Role',
            'key' => 'sales-directory-'.uniqid(),
            'is_active' => true,
        ]);
        $role->permissions()->sync(Permission::query()
            ->whereIn('key', ['sales.orders.create'])
            ->pluck('id'));
        $user->roles()->attach($role);

        return $user;
    }

    private function customer(string $name, string $phone): Party
    {
        $customer = Party::query()->create([
            'entity_type' => 'individual',
            'name' => $name,
            'mobile' => $phone,
            'is_active' => true,
        ]);
        $customer->partyRoles()->create(['role' => 'customer']);
        $customer->phones()->create([
            'number' => $phone,
            'type' => 'mobile',
            'is_primary' => true,
            'is_active' => true,
        ]);

        return $customer;
    }

    public function test_customer_directory_searches_phone_only_and_returns_matches_outside_current_page(): void
    {
        $user = $this->salesUser();
        $match = $this->customer('عميل الهاتف', '01055551234');
        $this->customer('01055551234 اسم فقط', '01100000000');

        $this->actingAs($user)
            ->getJson(route('admin.sales.orders.customers.directory', ['phone' => '0105555']))
            ->assertOk()
            ->assertJsonPath('customers.total', 1)
            ->assertJsonPath('customers.data.0.id', $match->id)
            ->assertJsonPath('customers.data.0.phone', '01055551234');
    }

    public function test_customer_directory_uses_server_side_pagination_for_remaining_customers(): void
    {
        $user = $this->salesUser();
        for ($index = 1; $index <= 13; $index++) {
            $this->customer('عميل صفحة '.$index, '010'.str_pad((string) $index, 8, '0', STR_PAD_LEFT));
        }

        $this->actingAs($user)
            ->getJson(route('admin.sales.orders.customers.directory'))
            ->assertOk()
            ->assertJsonPath('customers.per_page', 12)
            ->assertJsonPath('customers.total', 13)
            ->assertJsonPath('customers.last_page', 2)
            ->assertJsonCount(12, 'customers.data')
            ->assertJsonPath('customers.data.0.delivered_orders_count', 0)
            ->assertJsonPath('customers.data.0.preparing_orders_count', 0)
            ->assertJsonPath('customers.data.0.pending_confirmation_orders_count', 0)
            ->assertJsonPath('customers.data.0.shipping_orders_count', 0)
            ->assertJsonPath('customers.data.0.is_most_delivered', false);
    }

    public function test_customer_directory_ranks_by_delivered_orders_and_returns_all_three_order_counts(): void
    {
        $user = $this->salesUser();
        $mostDelivered = $this->customer('الأكثر تسليمًا', '01011111111');
        $secondDelivered = $this->customer('عميل آخر', '01022222222');
        $noDelivery = $this->customer('بدون تسليم', '01033333333');

        foreach (['delivered', 'delivered', 'preparing', 'shipping', 'draft'] as $status) {
            SalesOrder::query()->create([
                'order_number' => 'SO-'.uniqid(),
                'customer_id' => $mostDelivered->id,
                'created_by' => $user->id,
                'status' => $status,
                'subtotal' => 100,
                'discount_total' => 0,
                'total' => 100,
            ]);
        }

        foreach (['delivered', 'preparing', 'pending_confirmation'] as $status) {
            SalesOrder::query()->create([
                'order_number' => 'SO-'.uniqid(),
                'customer_id' => $secondDelivered->id,
                'created_by' => $user->id,
                'status' => $status,
                'subtotal' => 100,
                'discount_total' => 0,
                'total' => 100,
            ]);
        }

        SalesOrder::query()->create([
            'order_number' => 'SO-'.uniqid(),
            'customer_id' => $noDelivery->id,
            'created_by' => $user->id,
            'status' => 'draft',
            'subtotal' => 100,
            'discount_total' => 0,
            'total' => 100,
        ]);

        $this->actingAs($user)
            ->getJson(route('admin.sales.orders.customers.directory'))
            ->assertOk()
            ->assertJsonPath('customers.total', 3)
            ->assertJsonPath('customers.data.0.id', $mostDelivered->id)
            ->assertJsonPath('customers.data.0.delivered_orders_count', 2)
            ->assertJsonPath('customers.data.0.preparing_orders_count', 1)
            ->assertJsonPath('customers.data.0.shipping_orders_count', 1)
            ->assertJsonPath('customers.data.0.pending_confirmation_orders_count', 1)
            ->assertJsonPath('customers.data.0.is_most_delivered', true)
            ->assertJsonPath('customers.data.1.id', $secondDelivered->id)
            ->assertJsonPath('customers.data.1.delivered_orders_count', 1)
            ->assertJsonPath('customers.data.1.preparing_orders_count', 1)
            ->assertJsonPath('customers.data.1.shipping_orders_count', 0)
            ->assertJsonPath('customers.data.1.pending_confirmation_orders_count', 1)
            ->assertJsonPath('customers.data.1.is_most_delivered', false)
            ->assertJsonPath('customers.data.2.id', $noDelivery->id)
            ->assertJsonPath('customers.data.2.delivered_orders_count', 0)
            ->assertJsonPath('customers.data.2.is_most_delivered', false);
    }

    public function test_customer_selection_returns_all_active_phones_and_addresses(): void
    {
        $user = $this->salesUser();
        $customer = $this->customer('عميل الاختيار', '01012345678');
        $customer->phones()->create([
            'number' => '01222223333',
            'type' => 'mobile',
            'is_primary' => false,
            'is_active' => true,
        ]);
        $city = City::query()->where('is_active', true)->firstOrFail();
        $customer->addresses()->create([
            'city_id' => $city->id,
            'title' => 'العنوان الرئيسي',
            'address' => 'شارع الاختبار',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->getJson(route('admin.sales.orders.customers.selection', $customer))
            ->assertOk()
            ->assertJsonPath('customer.id', $customer->id)
            ->assertJsonCount(2, 'customer.phones')
            ->assertJsonPath('customer.phones.0.number', '01012345678')
            ->assertJsonPath('customer.phones.0.isWhatsapp', true)
            ->assertJsonPath('customer.phones.0.isTelegram', true)
            ->assertJsonCount(1, 'customer.addresses')
            ->assertJsonPath('customer.addresses.0.isPrimary', true);
    }
}
