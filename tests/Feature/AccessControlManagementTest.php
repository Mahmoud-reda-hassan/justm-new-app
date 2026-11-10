<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Company;
use App\Models\Branch;
use App\Models\UserDataScope;
use App\Models\PermissionAudit;
use App\Models\Party;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlManagementTest extends TestCase
{
    use RefreshDatabase;

    private function administratorWith(array $keys, bool $globalScope = true): User
    {
        $user = User::create([
            'name' => 'Access Admin',
            'username' => 'access.admin',
            'password' => 'secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);

        $role = Role::create([
            'name' => 'Access management',
            'key' => 'access-management',
            'is_active' => true,
        ]);

        $permissions = Permission::query()->whereIn('key', array_values(array_unique([...$keys, ...($globalScope ? ['structure.scope.manage_all'] : [])])))->get();
        $role->permissions()->sync($permissions->modelKeys());
        $user->roles()->attach($role);

        return $user;
    }


    public function test_user_outside_actor_scope_cannot_be_updated(): void
    {
        $admin = $this->administratorWith(['users.update'], false);
        $companyA = Company::create(['name' => 'Company A', 'is_active' => true]);
        $companyB = Company::create(['name' => 'Company B', 'is_active' => true]);
        UserDataScope::create(['user_id' => $admin->id, 'scope_type' => 'company', 'scope_id' => $companyA->id]);

        $target = User::create([
            'name' => 'Outside User',
            'username' => 'outside-user',
            'password' => 'secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);
        UserDataScope::create(['user_id' => $target->id, 'scope_type' => 'company', 'scope_id' => $companyB->id]);

        $this->actingAs($admin)->put(route('admin.access.users.update', $target), [
            'name' => 'Changed',
            'username' => 'outside-user',
            'email' => null,
        ])->assertForbidden();

        $this->assertSame('Outside User', $target->fresh()->name);
    }

    public function test_new_user_inherits_creator_scope_and_can_be_narrowed_to_child_branch(): void
    {
        $admin = $this->administratorWith(['users.create', 'users.assign_scopes'], false);
        $company = Company::create(['name' => 'Scoped Company', 'is_active' => true]);
        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Scoped Branch',
            'is_main' => true,
            'is_active' => true,
        ]);
        UserDataScope::create(['user_id' => $admin->id, 'scope_type' => 'company', 'scope_id' => $company->id]);

        $this->actingAs($admin)->post(route('admin.access.users.store'), [
            'name' => 'New Operator',
            'username' => 'new-scoped-operator',
            'password' => 'long-secure-password',
            'password_confirmation' => 'long-secure-password',
        ])->assertSessionHasNoErrors();

        $target = User::query()->where('username', 'new-scoped-operator')->firstOrFail();
        $this->assertTrue($target->fresh()->hasDataScope('company', $company->id));

        $this->put(route('admin.access.users.scopes', $target), [
            'branch_ids' => [$branch->id],
        ])->assertSessionHasNoErrors();

        $this->assertTrue($target->fresh()->hasDataScope('branch', $branch->id));
    }

    public function test_unscoped_manager_cannot_manage_a_scoped_user(): void
    {
        $admin = $this->administratorWith(['users.assign_groups'], false);
        $company = Company::create(['name' => 'Private Company', 'is_active' => true]);
        $target = User::create([
            'name' => 'Scoped Target',
            'username' => 'scoped-target',
            'password' => 'secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);
        UserDataScope::create(['user_id' => $target->id, 'scope_type' => 'company', 'scope_id' => $company->id]);
        $role = Role::create(['name' => 'Basic', 'key' => 'basic-role', 'is_active' => true]);

        $this->actingAs($admin)->put(route('admin.access.users.roles', $target), [
            'roles' => [$role->id],
        ])->assertForbidden();

        $this->assertSame(0, $target->fresh()->roles()->count());
    }


    public function test_user_without_data_scope_cannot_create_scoped_accounts(): void
    {
        $admin = $this->administratorWith(['users.create'], false);

        $this->actingAs($admin)->post(route('admin.access.users.store'), [
            'name' => 'Orphan User',
            'username' => 'orphan-user',
            'password' => 'long-secure-password',
            'password_confirmation' => 'long-secure-password',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['username' => 'orphan-user']);
    }

    public function test_permission_groups_screen_uses_native_adminlte_cards_and_permission_modules(): void
    {
        app(\Database\Seeders\DatabaseSeeder::class)->run();
        $admin = $this->administratorWith([
            'permissions.groups.view',
            'permissions.groups.create',
            'permissions.groups.update',
            'permissions.groups.assign',
            'permissions.catalog.assign',
        ]);
        $role = Role::create(['name' => 'مشغل المخزن', 'key' => 'warehouse-operator', 'is_active' => true]);

        $this->actingAs($admin)->get(route('admin.access.roles'))
            ->assertOk()
            ->assertSee('card card-outline card-primary', false)
            ->assertSee('card card-outline card-secondary', false)
            ->assertSee('مشغل المخزن')
            ->assertSee('حفظ صلاحيات المجموعة');
    }

    public function test_user_management_screen_uses_adminlte_table_and_modal_actions(): void
    {
        app(\Database\Seeders\DatabaseSeeder::class)->run();
        $admin = $this->administratorWith([
            'users.view', 'users.create', 'users.update', 'users.activate',
            'users.assign_groups', 'users.assign_scopes', 'users.reset_password',
        ]);
        $target = User::create([
            'name' => 'مشغل تجريبي',
            'username' => 'modal-operator',
            'password' => 'secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('admin.access.users'))
            ->assertOk()
            ->assertSee('card card-solid card-primary', false)
            ->assertSee('id="user-search"', false)
            ->assertSee('createUserModal', false)
            ->assertSee('editUserModal'.$target->id, false)
            ->assertSee('userRolesModal'.$target->id, false)
            ->assertSee('userScopesModal'.$target->id, false)
            ->assertSee('userPasswordModal'.$target->id, false);
    }

    public function test_user_directory_filters_by_search_party_role_status_and_account_link(): void
    {
        app(\Database\Seeders\DatabaseSeeder::class)->run();
        $admin = $this->administratorWith(['users.view']);
        $customer = Party::create([
            'entity_type' => 'individual',
            'name' => 'عميل البحث المحدد',
            'is_active' => true,
        ]);
        $customer->partyRoles()->create(['role' => 'customer']);
        User::create([
            'name' => 'حساب العميل',
            'username' => 'filtered-customer',
            'password' => 'secure-password',
            'account_type' => 'partner',
            'party_id' => $customer->id,
            'is_active' => true,
        ]);
        User::create([
            'name' => 'حساب داخلي آخر',
            'username' => 'unfiltered-internal',
            'password' => 'secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('admin.access.users', [
            'q' => 'عميل البحث المحدد',
            'party_role' => 'customer',
            'status' => 'active',
            'account' => 'linked',
        ]))
            ->assertOk()
            ->assertSee('حساب العميل')
            ->assertDontSee('حساب داخلي آخر');
    }

    public function test_user_management_routes_require_their_specific_permission(): void
    {
        $user = User::create([
            'name' => 'No Access',
            'username' => 'no.access',
            'password' => 'secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('admin.access.users'))
            ->assertForbidden();
    }

    public function test_user_creation_is_audited_and_does_not_assign_a_role_implicitly(): void
    {
        $admin = $this->administratorWith(['users.view', 'users.create']);

        $this->actingAs($admin)->post(route('admin.access.users.store'), [
            'name' => 'New Operator',
            'username' => 'new-operator',
            'email' => 'operator@example.test',
            'password' => 'long-secure-password',
            'password_confirmation' => 'long-secure-password',
        ])->assertSessionHasNoErrors();

        $created = User::query()->where('username', 'new-operator')->firstOrFail();

        $this->assertSame(0, $created->roles()->count());
        $this->assertDatabaseHas('permission_audits', [
            'actor_user_id' => $admin->id,
            'target_user_id' => $created->id,
            'event' => 'users.created',
        ]);
    }

    public function test_user_cannot_assign_a_group_with_permissions_they_do_not_hold(): void
    {
        $admin = $this->administratorWith(['users.assign_groups']);
        $target = User::create([
            'name' => 'Target',
            'username' => 'target.user',
            'password' => 'secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);
        $privilegedRole = Role::create([
            'name' => 'Privileged',
            'key' => 'privileged',
            'is_active' => true,
        ]);
        $privilegedPermission = Permission::create([
            'key' => 'system.superuser',
            'module' => 'system',
            'action' => 'superuser',
        ]);
        $privilegedRole->permissions()->attach($privilegedPermission);

        $this->actingAs($admin)->put(route('admin.access.users.roles', $target), [
            'roles' => [$privilegedRole->id],
        ])->assertForbidden();

        $this->assertSame(0, $target->fresh()->roles()->count());
    }

    public function test_inactive_role_cannot_be_assigned_to_a_user(): void
    {
        $admin = $this->administratorWith(['users.assign_groups']);
        $target = User::create([
            'name' => 'Target',
            'username' => 'target.user',
            'password' => 'secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);
        $inactiveRole = Role::create([
            'name' => 'Inactive role',
            'key' => 'inactive-role',
            'is_active' => false,
        ]);

        $this->actingAs($admin)->put(route('admin.access.users.roles', $target), [
            'roles' => [$inactiveRole->id],
        ])->assertSessionHasErrors('roles.0');

        $this->assertSame(0, $target->fresh()->roles()->count());
    }

    public function test_user_group_assignment_is_audited_and_can_clear_all_groups(): void
    {
        $admin = $this->administratorWith(['users.assign_groups']);
        $target = User::create([
            'name' => 'Target',
            'username' => 'target.user',
            'password' => 'secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);
        $role = Role::create(['name' => 'Read only', 'key' => 'read-only', 'is_active' => true]);
        $allowed = Permission::query()->firstOrCreate(
            ['key' => 'users.view'],
            ['module' => 'users', 'action' => 'view'],
        );
        $role->permissions()->attach($allowed);
        $admin->roles()->first()->permissions()->attach($allowed);

        $this->actingAs($admin)->put(route('admin.access.users.roles', $target), [
            'roles' => [$role->id],
        ])->assertSessionHasNoErrors();

        $this->assertTrue($target->fresh()->roles->contains('id', $role->id));
        $this->assertDatabaseHas('permission_audits', [
            'actor_user_id' => $admin->id,
            'target_user_id' => $target->id,
            'event' => 'users.groups_assigned',
        ]);

        $this->put(route('admin.access.users.roles', $target), [])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $target->fresh()->roles()->count());
    }

    public function test_initial_access_admin_can_add_operational_permissions_to_own_role(): void
    {
        $admin = $this->administratorWith(['permissions.groups.view', 'permissions.groups.assign', 'permissions.catalog.assign']);
        $role = $admin->roles()->firstOrFail();
        $role->update(['key' => 'system-access-admin']);
        $permission = Permission::query()->firstOrCreate(['key' => 'companies.view'], ['module' => 'companies', 'action' => 'view']);
        $protected = $role->permissions()->where('permissions.key', 'permissions.groups.view')->firstOrFail();

        $this->from(route('admin.access.roles'))->actingAs($admin)
            ->put(route('admin.access.roles.permissions', $role), ['permissions' => [$permission->id]])
            ->assertRedirect(route('admin.access.roles'))->assertSessionHasNoErrors();

        $this->assertTrue($role->fresh()->permissions->contains('id', $permission->id));
        $this->assertTrue($role->fresh()->permissions->contains('id', $protected->id));
        $this->assertDatabaseHas('permission_audits', ['actor_user_id' => $admin->id, 'event' => 'permissions.groups.permissions_assigned']);
        $this->get(route('admin.access.roles'))->assertOk()->assertSee('يمكنك إضافة صلاحيات التشغيل لمجموعتك.')->assertSee('companies.view');
    }

    public function test_initial_access_admin_cannot_add_new_sensitive_access_permissions_to_own_role(): void
    {
        $admin = $this->administratorWith(['permissions.groups.view', 'permissions.groups.assign']);
        $role = $admin->roles()->firstOrFail();
        $role->update(['key' => 'system-access-admin']);
        $sensitive = Permission::query()->firstOrCreate(['key' => 'users.delete'], ['module' => 'users', 'action' => 'delete']);

        $this->from(route('admin.access.roles'))->actingAs($admin)->put(route('admin.access.roles.permissions', $role), ['permissions' => [$sensitive->id]])
            ->assertRedirect(route('admin.access.roles'))->assertSessionHasErrors('permissions');
        $this->assertFalse($role->fresh()->permissions->contains('id', $sensitive->id));
    }

    public function test_initial_access_admin_cannot_remove_existing_access_protections(): void
    {
        $admin = $this->administratorWith(['permissions.groups.view', 'permissions.groups.assign']);
        $role = $admin->roles()->firstOrFail();
        $role->update(['key' => 'system-access-admin']);
        $protected = $role->permissions()->where('permissions.key', 'permissions.groups.view')->firstOrFail();

        $this->from(route('admin.access.roles'))->actingAs($admin)
            ->put(route('admin.access.roles.permissions', $role), ['permissions' => []])
            ->assertRedirect(route('admin.access.roles'))->assertSessionHasNoErrors();
        $this->assertTrue($role->fresh()->permissions->contains('id', $protected->id));
    }

    public function test_access_admin_can_assign_catalog_permissions_to_a_new_operating_role(): void
    {
        $admin = $this->administratorWith([
            'permissions.groups.view',
            'permissions.groups.create',
            'permissions.groups.assign',
            'permissions.catalog.assign',
        ]);
        $role = Role::create([
            'name' => 'Warehouse Operator',
            'key' => 'warehouse-operator',
            'is_active' => true,
        ]);
        $permission = Permission::query()->firstOrCreate(
            ['key' => 'warehouses.view'],
            ['module' => 'warehouses', 'action' => 'view'],
        );

        $this->from(route('admin.access.roles'))->actingAs($admin)
            ->put(route('admin.access.roles.permissions', $role), [
                'permissions' => [$permission->id],
            ])
            ->assertRedirect(route('admin.access.roles'));

        $this->assertTrue($role->fresh()->permissions->contains('id', $permission->id));
    }
}
