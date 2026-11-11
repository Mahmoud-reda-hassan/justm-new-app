<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Party;
use App\Models\Permission;
use App\Models\Role;
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
            ->assertJsonCount(1, 'customer.addresses')
            ->assertJsonPath('customer.addresses.0.isPrimary', true);
    }
}
