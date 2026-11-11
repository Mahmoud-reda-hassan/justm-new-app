<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\City;
use App\Models\Country;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ShippingZone;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseType;
use App\Services\BranchManager;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingZoneManagementTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $keys): User
    {
        app(DatabaseSeeder::class)->run();

        $user = User::query()->create([
            'name' => 'Shipping Admin',
            'username' => 'shipping-admin-'.uniqid(),
            'password' => 'secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);
        $role = Role::query()->create([
            'name' => 'Shipping role',
            'key' => 'shipping-role-'.uniqid(),
            'is_active' => true,
        ]);
        $role->permissions()->sync(Permission::query()->whereIn('key', $keys)->pluck('id'));
        $user->roles()->attach($role);

        return $user;
    }

    private function dispatchWarehouse(): Warehouse
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

        return Warehouse::query()->create([
            'branch_id' => $branch->id,
            'warehouse_type_id' => $type->id,
            'name' => 'مخزن البيع '.uniqid(),
            'is_active' => true,
        ]);
    }

    public function test_shipping_zone_screen_uses_adminlte_and_searches_zone_and_city(): void
    {
        $user = $this->userWith(['shipping_zones.view', 'structure.scope.manage_all']);
        $warehouse = $this->dispatchWarehouse();
        $city = City::query()->where('is_active', true)->firstOrFail();
        $zone = ShippingZone::query()->create([
            'name_ar' => 'منطقة القاهرة للاختبار',
            'name_en' => 'Cairo test zone',
            'shipping_fee_min' => 5,
            'shipping_fee_max' => 25,
            'delivery_days' => 2,
            'shipping_type' => 'internal',
            'warehouse_id' => $warehouse->id,
            'is_active' => true,
        ]);
        $city->forceFill(['shipping_zone_id' => $zone->id])->save();

        $this->actingAs($user)->get(route('admin.shipping.zones.index', ['q' => 'القاهرة']))
            ->assertOk()
            ->assertSee('table table-bordered table-hover table-striped', false)
            ->assertSee('منطقة القاهرة للاختبار')
            ->assertSee('ابحث باسم المنطقة', false);
    }

    public function test_shipping_zone_creation_links_only_unassigned_active_cities(): void
    {
        $user = $this->userWith(['shipping_zones.create', 'structure.scope.manage_all']);
        $warehouse = $this->dispatchWarehouse();
        $city = City::query()->where('is_active', true)->firstOrFail();

        $this->actingAs($user)->post(route('admin.shipping.zones.store'), [
            'name_ar' => 'منطقة جديدة',
            'name_en' => 'New zone',
            'shipping_fee_min' => 5,
            'shipping_fee_max' => 30,
            'delivery_days' => 3,
            'shipping_type' => 'internal',
            'warehouse_id' => $warehouse->id,
            'city_ids' => [$city->id],
        ])->assertSessionHasNoErrors();

        $zone = ShippingZone::query()->where('name_ar', 'منطقة جديدة')->firstOrFail();
        $this->assertSame($zone->id, $city->fresh()->shipping_zone_id);
        $this->assertSame(1, $zone->cities()->count());
    }

    public function test_city_cannot_be_assigned_to_two_shipping_zones(): void
    {
        $user = $this->userWith(['shipping_zones.create', 'structure.scope.manage_all']);
        $warehouse = $this->dispatchWarehouse();
        $city = City::query()->where('is_active', true)->firstOrFail();
        $existing = ShippingZone::query()->create([
            'name_ar' => 'المنطقة الأولى',
            'name_en' => 'First zone',
            'shipping_fee_min' => 0,
            'shipping_fee_max' => 20,
            'delivery_days' => 1,
            'shipping_type' => 'internal',
            'warehouse_id' => $warehouse->id,
            'is_active' => true,
        ]);
        $city->forceFill(['shipping_zone_id' => $existing->id])->save();

        $this->actingAs($user)->from(route('admin.shipping.zones.index'))
            ->post(route('admin.shipping.zones.store'), [
                'name_ar' => 'المنطقة الثانية',
                'name_en' => 'Second zone',
                'shipping_fee_min' => 0,
                'shipping_fee_max' => 20,
                'delivery_days' => 1,
                'shipping_type' => 'internal',
                'warehouse_id' => $warehouse->id,
                'city_ids' => [$city->id],
            ])->assertSessionHasErrors('city_ids');

        $this->assertDatabaseMissing('shipping_zones', ['name_ar' => 'المنطقة الثانية']);
        $this->assertSame($existing->id, $city->fresh()->shipping_zone_id);
    }

    public function test_international_zone_can_cover_active_countries_without_local_warehouse_or_tariff(): void
    {
        $user = $this->userWith(['shipping_zones.create', 'structure.scope.manage_all']);
        app(DatabaseSeeder::class)->run();
        $country = Country::query()->where('is_active', true)->firstOrFail();

        $this->actingAs($user)->post(route('admin.shipping.zones.store'), [
            'zone_type' => 'international',
            'name_ar' => 'دول الخليج',
            'name_en' => 'Gulf Countries',
            'country_ids' => [$country->id],
        ])->assertSessionHasNoErrors();

        $zone = ShippingZone::query()->where('name_ar', 'دول الخليج')->firstOrFail();
        $this->assertSame('international', $zone->zone_type);
        $this->assertNull($zone->warehouse_id);
        $this->assertNull($zone->shipping_fee_max);
        $this->assertNull($zone->delivery_days);
        $this->assertTrue($zone->countries()->whereKey($country->id)->exists());
        $this->assertSame(0, $zone->cities()->count());
    }

    public function test_international_zone_management_requires_global_structure_scope(): void
    {
        $user = $this->userWith(['shipping_zones.create']);
        app(DatabaseSeeder::class)->run();
        $country = Country::query()->where('is_active', true)->firstOrFail();

        $this->actingAs($user)->post(route('admin.shipping.zones.store'), [
            'zone_type' => 'international',
            'name_ar' => 'منطقة دولية ممنوعة',
            'name_en' => 'Forbidden international zone',
            'country_ids' => [$country->id],
        ])->assertForbidden();

        $this->assertDatabaseMissing('shipping_zones', ['name_ar' => 'منطقة دولية ممنوعة']);
    }

}
