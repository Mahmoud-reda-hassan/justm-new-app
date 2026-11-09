<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_logout(): void
    {
        $user = User::create([
            'name' => 'Test Admin',
            'username' => 'admin.test',
            'password' => 'secret-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);

        $this->post(route('login.store'), [
            'username' => 'admin.test',
            'password' => 'secret-password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::create([
            'name' => 'Disabled User',
            'username' => 'disabled',
            'password' => 'secret-password',
            'account_type' => 'admin',
            'is_active' => false,
        ]);

        $this->post(route('login.store'), [
            'username' => 'disabled',
            'password' => 'secret-password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_permission_is_granted_only_through_an_active_assigned_role(): void
    {
        $user = User::create([
            'name' => 'Operator',
            'username' => 'operator',
            'password' => 'secret-password',
            'account_type' => 'delivery',
            'is_active' => true,
        ]);
        $role = Role::create(['name' => 'Delivery clerk', 'key' => 'delivery-clerk', 'is_active' => true]);
        $permission = Permission::create([
            'key' => 'delivery.results.view',
            'module' => 'delivery',
            'action' => 'view',
        ]);

        $this->assertFalse($user->hasPermission('delivery.results.view'));

        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $this->assertTrue($user->hasPermission('delivery.results.view'));

        $role->update(['is_active' => false]);
        $this->assertFalse($user->hasPermission('delivery.results.view'));
    }

    public function test_data_scope_does_not_grant_unassigned_or_unknown_scope(): void
    {
        $user = User::create([
            'name' => 'Branch user',
            'username' => 'branch.user',
            'password' => 'secret-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);

        $user->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => 7]);

        $this->assertTrue($user->hasDataScope('branch', 7));
        $this->assertFalse($user->hasDataScope('branch', 8));
        $this->assertFalse($user->hasDataScope('company-wide', 1));
    }
}
