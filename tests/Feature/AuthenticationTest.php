<?php

namespace Tests\Feature;

use App\Models\Party;
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

    public function test_admin_path_rejects_users_without_an_explicit_admin_permission(): void
    {
        $user = User::create([
            'name' => 'Test Admin',
            'username' => 'admin.path',
            'password' => 'secret-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);

        $this->get('/admin')->assertRedirect(route('login'));

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
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

    public function test_special_users_screen_redirects_a_single_operational_role_to_its_workspace(): void
    {
        $party = Party::create(['name' => 'Marketer', 'entity_type' => 'individual', 'is_active' => true]);
        $party->partyRoles()->create(['role' => 'marketer']);
        $user = User::create([
            'name' => 'Marketer User',
            'username' => 'marketer.user',
            'password' => 'secret-password',
            'account_type' => 'partner',
            'party_id' => $party->id,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('workspaces.index'))
            ->assertRedirect(route('workspaces.enter', ['workspace' => 'marketer']));

        $this->actingAs($user)
            ->get(route('workspaces.enter', ['workspace' => 'marketer']))
            ->assertOk()
            ->assertSee('لوحة العمل الخاصة بهذا الدور لم تُنفذ بعد');
    }

    public function test_special_users_screen_shows_only_assigned_roles_when_user_has_multiple_roles(): void
    {
        $party = Party::create(['name' => 'Multi-role', 'entity_type' => 'individual', 'is_active' => true]);
        $party->partyRoles()->createMany([
            ['role' => 'marketer'],
            ['role' => 'delivery_agent'],
        ]);
        $user = User::create([
            'name' => 'Multi User',
            'username' => 'multi.user',
            'password' => 'secret-password',
            'account_type' => 'partner',
            'party_id' => $party->id,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('workspaces.index'))
            ->assertOk()
            ->assertSee('منطقة المسوقين والوسطاء')
            ->assertSee('منطقة المندوبين')
            ->assertDontSee('منطقة الوسطاء')
            ->assertDontSee('منطقة الإداريين');
    }

    public function test_account_type_alone_does_not_grant_admin_workspace(): void
    {
        $user = User::create([
            'name' => 'Not Admin',
            'username' => 'not.admin',
            'password' => 'secret-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('workspaces.enter', ['workspace' => 'admin']))
            ->assertForbidden();

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_admin_workspace_requires_an_explicit_administration_permission(): void
    {
        $user = User::create([
            'name' => 'Access Manager',
            'username' => 'access.manager',
            'password' => 'secret-password',
            'account_type' => 'partner',
            'is_active' => true,
        ]);
        $role = Role::create(['name' => 'Access group', 'key' => 'access-group', 'is_active' => true]);
        $permission = Permission::firstOrCreate(
            ['key' => 'users.view'],
            ['module' => 'users', 'action' => 'view'],
        );
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get(route('workspaces.index'))
            ->assertRedirect(route('workspaces.enter', ['workspace' => 'admin']));

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect(route('dashboard'));
    }

}
