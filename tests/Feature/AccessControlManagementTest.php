<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\PermissionAudit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlManagementTest extends TestCase
{
    use RefreshDatabase;

    private function administratorWith(array $keys): User
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

        $permissions = Permission::query()->whereIn('key', $keys)->get();
        $role->permissions()->sync($permissions->modelKeys());
        $user->roles()->attach($role);

        return $user;
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
    public function test_current_users_role_permissions_cannot_be_changed_and_screen_explains_why(): void
    {
        $admin = $this->administratorWith([
            'permissions.groups.view',
            'permissions.groups.assign',
            'permissions.catalog.assign',
        ]);
        $role = $admin->roles()->firstOrFail();
        $permission = Permission::query()->firstOrCreate(
            ['key' => 'companies.view'],
            ['module' => 'companies', 'action' => 'view'],
        );
        $originalPermissionIds = $role->permissions()->pluck('permissions.id')->all();

        $this->actingAs($admin)
            ->from(route('admin.access.roles'))
            ->put(route('admin.access.roles.permissions', $role), [
                'permissions' => [$permission->id],
            ])
            ->assertRedirect(route('admin.access.roles'))
            ->assertSessionHasErrors('permissions');

        $this->assertSame($originalPermissionIds, $role->fresh()->permissions()->pluck('permissions.id')->all());

        $this->get(route('admin.access.roles'))
            ->assertOk()
            ->assertSee('مجموعة حسابك الحالي محمية.')
            ->assertDontSee('action="' . route('admin.access.roles.permissions', $role) . '"');
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

        $this->actingAs($admin)
            ->put(route('admin.access.roles.permissions', $role), [
                'permissions' => [$permission->id],
            ])
            ->assertRedirect(route('admin.access.roles'));

        $this->assertTrue($role->fresh()->permissions->contains('id', $permission->id));
    }

}
