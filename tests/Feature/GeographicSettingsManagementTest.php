<?php

namespace Tests\\Feature;

use App\\Models\\Country;
use App\\Models\\Governorate;
use App\\Models\\Permission;
use App\\Models\\Role;
use App\\Models\\User;
use Database\\Seeders\\DatabaseSeeder;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class GeographicSettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $keys): User
    {
        app(DatabaseSeeder::class)->run();
        $user = User::query()->create([
            'name' => 'Geography Admin',
            'username' => 'geography-admin-'.uniqid(),
            'password' => 'secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);
        $role = Role::query()->create(['name' => 'Geography role', 'key' => 'geography-role-'.uniqid(), 'is_active' => true]);
        $role->permissions()->sync(Permission::query()->whereIn('key', $keys)->pluck('id'));
        $user->roles()->attach($role);
        return $user;
    }

    public function test_geographic_screens_are_available_with_independent_view_permissions(): void
    {
        $user = $this->userWith(['geography.countries.view', 'geography.governorates.view', 'geography.cities.view']);
        $this->actingAs($user)->get(route('admin.geography.index', 'countries'))->assertOk()->assertSee('الدول');
        $this->get(route('admin.geography.index', 'governorates'))->assertOk()->assertSee('المحافظات والمقاطعات');
        $this->get(route('admin.geography.index', 'cities'))->assertOk()->assertSee('المدن');
    }

    public function test_country_and_governorate_can_be_created_and_linked(): void
    {
        $user = $this->userWith(['geography.countries.create', 'geography.governorates.create', 'geography.countries.view', 'geography.governorates.view']);
        $this->actingAs($user)->post(route('admin.geography.store', 'countries'), [
            'name_ar' => 'دولة الاختبار', 'name_en' => 'Test Country',
        ])->assertSessionHasNoErrors();
        $country = Country::query()->where('name_en', 'Test Country')->firstOrFail();

        $this->post(route('admin.geography.store', 'governorates'), [
            'country_id' => $country->id, 'name_ar' => 'محافظة الاختبار', 'name_en' => 'Test Governorate',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('governorates', ['country_id' => $country->id, 'name_en' => 'Test Governorate']);
    }

    public function test_country_cannot_be_disabled_while_it_has_active_governorates(): void
    {
        $user = $this->userWith(['geography.countries.activate']);
        $country = Country::query()->firstOrFail();
        $governorate = Governorate::query()->create([
            'country_id' => $country->id,
            'name_ar' => 'محافظة نشطة للاختبار',
            'name_en' => 'Active test governorate',
            'is_active' => true,
        ]);

        $this->actingAs($user)->from(route('admin.geography.index', 'countries'))
            ->patch(route('admin.geography.active', ['countries', $country->id]), ['is_active' => 0])
            ->assertSessionHasErrors('record');
        $this->assertTrue($country->fresh()->is_active);
        $this->assertTrue($governorate->fresh()->is_active);
    }

    public function test_user_without_geography_permission_cannot_open_screen(): void
    {
        $user = $this->userWith([]);
        $this->actingAs($user)->get(route('admin.geography.index', 'countries'))->assertForbidden();
    }
}
