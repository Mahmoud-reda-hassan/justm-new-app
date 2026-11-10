<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\City;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserDataScope;
use App\Models\Warehouse;
use App\Models\WarehouseType;
use App\Services\BranchManager;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatingStructureManagementTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $keys): User
    {
        app(DatabaseSeeder::class)->run();

        $user = User::create([
            'name' => 'Structure Admin',
            'username' => 'structure-admin-'.uniqid(),
            'password' => 'secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);
        $role = Role::create(['name' => 'Structure role', 'key' => 'structure-role-'.uniqid(), 'is_active' => true]);
        $role->permissions()->sync(Permission::query()->whereIn('key', $keys)->pluck('id'));
        $user->roles()->attach($role);

        return $user;
    }

    private function company(string $name = 'شركة الاختبار'): Company
    {
        return Company::create(['name' => $name, 'is_active' => true]);
    }

    public function test_structure_routes_require_their_specific_permissions(): void
    {
        $user = $this->userWith([]);

        $this->actingAs($user)->get(route('admin.structure.companies'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.structure.branches'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.structure.warehouses'))->assertForbidden();
    }

    public function test_user_management_screen_renders_data_scope_selectors(): void
    {
        $user = $this->userWith(['users.view', 'users.assign_scopes']);
        $response = $this->actingAs($user)->get(route('admin.access.users'))->assertOk();
        $response->assertSee('نطاق البيانات');
    }

    public function test_structure_screens_use_adminlte_tables_and_modal_forms(): void
    {
        $user = $this->userWith([
            'companies.view', 'branches.view', 'warehouses.view', 'structure.scope.manage_all',
            'companies.create', 'branches.create', 'warehouses.create',
        ]);

        $this->actingAs($user)->get(route('admin.structure.companies'))
            ->assertOk()->assertSee('table table-bordered table-hover table-striped', false)
            ->assertSee('createCompanyModal', false);
        $this->get(route('admin.structure.branches'))
            ->assertOk()->assertSee('table table-bordered table-hover table-striped', false)
            ->assertSee('createBranchModal', false);
        $this->get(route('admin.structure.warehouses'))
            ->assertOk()->assertSee('table table-bordered table-hover table-striped', false)
            ->assertSee('createWarehouseModal', false);
    }

    public function test_company_creation_is_available_only_with_create_permission(): void
    {
        $user = $this->userWith(['companies.create']);

        $this->actingAs($user)->post(route('admin.structure.companies.store'), [
            'name' => 'شركة جديدة',
            'phone' => '01000000000',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('companies', ['name' => 'شركة جديدة', 'is_active' => true]);
    }

    public function test_company_list_is_limited_to_explicit_company_scope(): void
    {
        $user = $this->userWith(['companies.view']);
        $visible = $this->company('شركة ضمن النطاق');
        $this->company('شركة خارج النطاق');
        UserDataScope::create(['user_id' => $user->id, 'scope_type' => 'company', 'scope_id' => $visible->id]);

        $response = $this->actingAs($user)->get(route('admin.structure.companies'))->assertOk();
        $response->assertSee('شركة ضمن النطاق')->assertDontSee('شركة خارج النطاق');
    }

    public function test_branch_creation_requires_company_scope_and_uses_branch_manager(): void
    {
        $user = $this->userWith(['branches.create']);
        $company = $this->company();
        UserDataScope::create(['user_id' => $user->id, 'scope_type' => 'company', 'scope_id' => $company->id]);

        $city = City::query()->where('is_active', true)->firstOrFail();
        $this->actingAs($user)->post(route('admin.structure.branches.store'), [
            'company_id' => $company->id,
            'city_id' => $city->id,
            'name' => 'الفرع الرئيسي',
            'address' => 'عنوان الفرع',
            'is_main' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('branches', [
            'company_id' => $company->id,
            'name' => 'الفرع الرئيسي',
            'is_main' => true,
        ]);
    }

    public function test_branch_creation_requires_a_city_and_address(): void
    {
        $user = $this->userWith(['branches.create', 'structure.scope.manage_all']);
        $company = $this->company('شركة الفرع');
        $this->actingAs($user)->from(route('admin.structure.branches'))
            ->post(route('admin.structure.branches.store'), [
                'company_id' => $company->id,
                'name' => 'فرع بلا عنوان',
            ])->assertSessionHasErrors(['city_id', 'address']);

        $this->assertDatabaseMissing('branches', ['name' => 'فرع بلا عنوان']);
    }

    public function test_branch_search_filters_by_company_and_keeps_data_scope(): void
    {
        $user = $this->userWith(['branches.view']);
        $visible = $this->company('شركة ظاهرة');
        $hidden = $this->company('شركة مخفية');
        $city = City::query()->where('is_active', true)->firstOrFail();
        $visibleBranch = app(BranchManager::class)->create([
            'company_id' => $visible->id, 'city_id' => $city->id,
            'name' => 'فرع القاهرة', 'address' => 'وسط البلد', 'is_main' => true, 'is_active' => true,
        ]);
        app(BranchManager::class)->create([
            'company_id' => $hidden->id, 'city_id' => $city->id,
            'name' => 'فرع القاهرة الآخر', 'address' => 'مدينة نصر', 'is_main' => true, 'is_active' => true,
        ]);
        UserDataScope::create(['user_id' => $user->id, 'scope_type' => 'company', 'scope_id' => $visible->id]);

        $this->actingAs($user)->get(route('admin.structure.branches', ['q' => 'القاهرة']))
            ->assertOk()->assertSee('فرع القاهرة')->assertDontSee('فرع القاهرة الآخر');
    }

    public function test_warehouse_search_matches_branch_company_and_type(): void
    {
        $user = $this->userWith(['warehouses.view', 'structure.scope.manage_all']);
        app(DatabaseSeeder::class)->run();
        $company = $this->company('شركة مستودعات');
        $city = City::query()->where('is_active', true)->firstOrFail();
        $branch = app(BranchManager::class)->create([
            'company_id' => $company->id, 'city_id' => $city->id,
            'name' => 'فرع التوزيع', 'address' => 'عنوان التوزيع', 'is_main' => true, 'is_active' => true,
        ]);
        $type = WarehouseType::query()->where('is_active', true)->firstOrFail();
        Warehouse::create([
            'branch_id' => $branch->id, 'warehouse_type_id' => $type->id,
            'name' => 'مخزن البضائع', 'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('admin.structure.warehouses', ['q' => 'شركة مستودعات']))
            ->assertOk()->assertSee('مخزن البضائع');
    }

    public function test_branch_cannot_be_moved_to_another_company_when_it_has_warehouses(): void
    {
        app(DatabaseSeeder::class)->run();
        $first = $this->company('الأولى');
        $second = $this->company('الثانية');
        $branch = app(BranchManager::class)->create([
            'company_id' => $first->id,
            'name' => 'فرع مرتبط',
            'is_main' => true,
            'is_active' => true,
        ]);
        $this->assertNotNull($branch->id);
        \App\Models\Warehouse::create([
            'branch_id' => $branch->id,
            'warehouse_type_id' => \App\Models\WarehouseType::query()->where('key', 'internal')->value('id'),
            'name' => 'مخزن مرتبط',
            'is_active' => true,
        ]);

        $user = $this->userWith(['branches.update', 'structure.scope.manage_all']);
        $this->actingAs($user)->put(route('admin.structure.branches.update', $branch), [
            'company_id' => $second->id,
            'name' => $branch->name,
            'is_main' => 1,
        ])->assertSessionHasErrors();

        $this->assertSame($first->id, $branch->fresh()->company_id);
    }

    public function test_branch_cannot_have_more_than_one_sales_dispatch_warehouse(): void
    {
        $user = $this->userWith(['warehouses.create', 'structure.scope.manage_all']);
        $company = $this->company('شركة مخزن البيع');
        $city = City::query()->where('is_active', true)->firstOrFail();
        $branch = app(BranchManager::class)->create([
            'company_id' => $company->id, 'city_id' => $city->id,
            'name' => 'فرع البيع', 'address' => 'عنوان الفرع',
            'is_main' => true, 'is_active' => true,
        ]);
        $salesType = WarehouseType::query()->where('key', 'sales_dispatch')->firstOrFail();
        Warehouse::create([
            'branch_id' => $branch->id, 'warehouse_type_id' => $salesType->id,
            'name' => 'مخزن البيع الأول', 'is_active' => true,
        ]);

        $this->actingAs($user)->from(route('admin.structure.warehouses'))
            ->post(route('admin.structure.warehouses.store'), [
                'branch_id' => $branch->id,
                'warehouse_type_id' => $salesType->id,
                'name' => 'مخزن البيع الثاني',
            ])->assertSessionHasErrors('warehouse_type_id');

        $this->assertDatabaseMissing('warehouses', ['name' => 'مخزن البيع الثاني']);
    }

    public function test_company_cannot_be_disabled_while_it_has_active_branches(): void
    {
        $company = $this->company();
        app(BranchManager::class)->create([
            'company_id' => $company->id,
            'name' => 'فرع نشط',
            'is_main' => true,
            'is_active' => true,
        ]);
        $user = $this->userWith(['companies.activate', 'structure.scope.manage_all']);

        $this->actingAs($user)->patch(route('admin.structure.companies.active', $company), [
            'is_active' => 0,
        ])->assertSessionHasErrors('company');

        $this->assertTrue($company->fresh()->is_active);
    }

    public function test_access_administrator_can_delegate_catalog_permissions_but_not_to_own_role(): void
    {
        $admin = $this->userWith(['permissions.catalog.assign', 'permissions.groups.assign', 'users.assign_groups', 'structure.scope.manage_all']);
        $target = User::create([
            'name' => 'Scoped Operator',
            'username' => 'delegated-operator',
            'password' => 'secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);
        $role = Role::create(['name' => 'Company operator', 'key' => 'company-operator', 'is_active' => true]);
        $permission = Permission::query()->where('key', 'companies.view')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.access.roles.permissions', $role), [
            'permissions' => [$permission->id],
        ])->assertSessionHasNoErrors();

        $this->put(route('admin.access.users.roles', $target), [
            'roles' => [$role->id],
        ])->assertSessionHasNoErrors();

        $this->assertTrue($target->fresh()->hasPermission('companies.view'));

        $ownRole = $admin->roles()->firstOrFail();
        $this->from(route('admin.access.roles'))->put(route('admin.access.roles.permissions', $ownRole), [
            'permissions' => [$permission->id],
        ])->assertSessionHasErrors('permissions');

        $this->put(route('admin.access.users.roles', $admin), [
            'roles' => [$role->id],
        ])->assertStatus(422);
    }

    public function test_inactive_company_cannot_reactivate_branch_or_warehouse(): void
    {
        app(DatabaseSeeder::class)->run();
        $company = $this->company('شركة موقوفة');
        $branch = app(BranchManager::class)->create([
            'company_id' => $company->id,
            'name' => 'فرع متوقف',
            'is_main' => true,
            'is_active' => false,
        ]);
        $warehouseTypeId = WarehouseType::query()->where('key', 'internal')->value('id');
        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'warehouse_type_id' => $warehouseTypeId,
            'name' => 'مخزن متوقف',
            'is_active' => false,
        ]);
        $company->update(['is_active' => false]);
        $user = $this->userWith(['branches.activate', 'warehouses.activate', 'structure.scope.manage_all']);

        $this->actingAs($user)->patch(route('admin.structure.branches.active', $branch), [
            'is_active' => 1,
        ])->assertSessionHasErrors('branch');

        $this->patch(route('admin.structure.warehouses.active', $warehouse), [
            'is_active' => 1,
        ])->assertSessionHasErrors('warehouse');

        $this->assertFalse($branch->fresh()->is_active);
        $this->assertFalse($warehouse->fresh()->is_active);
    }

    public function test_data_scope_assignment_is_audited(): void
    {
        $admin = $this->userWith(['users.assign_scopes', 'structure.scope.manage_all']);
        $target = User::create([
            'name' => 'Target',
            'username' => 'scope-target',
            'password' => 'secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);
        $company = $this->company('مؤسسة النطاق');

        $this->actingAs($admin)->put(route('admin.access.users.scopes', $target), [
            'company_ids' => [$company->id],
        ])->assertSessionHasNoErrors();

        $this->assertTrue($target->fresh()->hasDataScope('company', $company->id));
        $this->assertDatabaseHas('permission_audits', [
            'actor_user_id' => $admin->id,
            'target_user_id' => $target->id,
            'event' => 'users.data_scopes_assigned',
        ]);
    }
}
