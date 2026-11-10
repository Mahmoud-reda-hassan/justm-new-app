<?php

namespace Tests\Feature;

use App\Models\Party;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartyManagementTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $permissions, bool $globalScope = true): User
    {
        app(DatabaseSeeder::class)->run();

        $user = User::create([
            'name' => 'Party Manager',
            'username' => 'party-manager-'.uniqid(),
            'password' => 'secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);
        $role = Role::create(['name' => 'Party role', 'key' => 'party-role-'.uniqid(), 'is_active' => true]);
        $permissionKeys = $globalScope ? array_values(array_unique([...$permissions, 'structure.scope.manage_all'])) : $permissions;
        $role->permissions()->sync(Permission::query()->whereIn('key', $permissionKeys)->pluck('id'));
        $user->roles()->attach($role);

        return $user;
    }

    public function test_party_manager_can_create_one_party_with_multiple_roles(): void
    {
        $user = $this->userWith(['parties.view', 'parties.create']);

        $this->actingAs($user)->post(route('admin.parties.store'), [
            'entity_type' => 'company',
            'name' => 'شركة تجمع أكثر من صفة',
            'phone' => '01000000000',
            'roles' => ['supplier', 'shipping_company'],
        ])->assertSessionHasNoErrors();

        $party = Party::query()->where('name', 'شركة تجمع أكثر من صفة')->firstOrFail();
        $this->assertEqualsCanonicalizing(['supplier', 'shipping_company'], $party->assignedRoles());
    }

    public function test_party_list_can_be_filtered_by_role_and_search_term(): void
    {
        $user = $this->userWith(['parties.view', 'parties.create']);
        $this->actingAs($user)->post(route('admin.parties.store'), [
            'entity_type' => 'individual', 'name' => 'عميل أحمد', 'roles' => ['customer'],
        ]);
        $this->actingAs($user)->post(route('admin.parties.store'), [
            'entity_type' => 'company', 'name' => 'مورد النور', 'roles' => ['supplier'],
        ]);

        $this->actingAs($user)->get(route('admin.parties.index', ['role' => 'supplier', 'q' => 'النور']))
            ->assertOk()->assertSee('مورد النور')->assertDontSee('عميل أحمد');
    }

    public function test_party_update_replaces_roles_and_active_state_can_be_changed(): void
    {
        $user = $this->userWith(['parties.view', 'parties.update', 'parties.activate']);
        $party = Party::query()->create(['entity_type' => 'individual', 'name' => 'اسم قبل التعديل']);
        $party->partyRoles()->create(['role' => 'customer']);

        $this->actingAs($user)->put(route('admin.parties.update', $party), [
            'entity_type' => 'individual',
            'name' => 'اسم بعد التعديل',
            'roles' => ['marketer', 'delivery_agent'],
        ])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(['marketer', 'delivery_agent'], $party->fresh()->assignedRoles());

        $this->patch(route('admin.parties.active', $party), ['is_active' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($party->fresh()->is_active);
    }

    public function test_party_routes_require_specific_permissions(): void
    {
        $user = $this->userWith([]);
        $this->actingAs($user)->get(route('admin.parties.index'))->assertForbidden();
        $this->post(route('admin.parties.store'), [
            'entity_type' => 'individual', 'name' => 'محاولة', 'roles' => ['customer'],
        ])->assertForbidden();
    }

    public function test_operational_party_can_be_linked_to_a_login_account_without_implicit_permissions(): void
    {
        $admin = $this->userWith(['users.create', 'structure.scope.manage_all']);
        $party = Party::query()->create(['entity_type' => 'individual', 'name' => 'مسوق تجريبي']);
        $party->partyRoles()->create(['role' => 'marketer']);

        $this->actingAs($admin)->post(route('admin.access.users.store'), [
            'name' => 'حساب المسوق',
            'username' => 'linked-marketer',
            'email' => 'linked-marketer@example.test',
            'password' => 'long-secure-password',
            'password_confirmation' => 'long-secure-password',
            'party_id' => $party->id,
        ])->assertSessionHasNoErrors();

        $account = User::query()->where('username', 'linked-marketer')->firstOrFail();
        $this->assertSame($party->id, $account->party_id);
        $this->assertSame('partner', $account->account_type);
        $this->assertSame(0, $account->roles()->count());
    }

    public function test_customer_party_can_be_linked_to_store_account_without_special_workspace(): void
    {
        $admin = $this->userWith(['users.create', 'structure.scope.manage_all']);
        $party = Party::query()->create(['entity_type' => 'individual', 'name' => 'عميل فقط']);
        $party->partyRoles()->create(['role' => 'customer']);

        $this->actingAs($admin)->post(route('admin.access.users.store'), [
            'name' => 'حساب غير مسموح',
            'username' => 'customer-only-login',
            'password' => 'long-secure-password',
            'password_confirmation' => 'long-secure-password',
            'party_id' => $party->id,
        ])->assertSessionHasNoErrors();

        $account = User::query()->where('username', 'customer-only-login')->firstOrFail();
        $this->assertSame($party->id, $account->party_id);
        $this->actingAs($account)->get(route('workspaces.index'))
            ->assertRedirect(route('dashboard'));
    }


    public function test_shipping_company_party_cannot_be_linked_to_a_login_account(): void
    {
        $admin = $this->userWith(['users.create', 'structure.scope.manage_all']);
        $party = Party::query()->create(['entity_type' => 'company', 'name' => 'شركة شحن خارجية']);
        $party->partyRoles()->create(['role' => 'shipping_company']);

        $this->actingAs($admin)->post(route('admin.access.users.store'), [
            'name' => 'حساب شركة الشحن',
            'username' => 'shipping-company-login',
            'password' => 'long-secure-password',
            'password_confirmation' => 'long-secure-password',
            'party_id' => $party->id,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('users', ['username' => 'shipping-company-login']);
    }

    public function test_legacy_intermediary_role_is_presented_as_the_shared_marketer_role(): void
    {
        $party = Party::query()->create(['entity_type' => 'individual', 'name' => 'وسيط قديم']);
        $party->partyRoles()->create(['role' => 'sales_intermediary']);

        $this->assertSame(['marketer'], $party->assignedRoles());

        $user = User::query()->create([
            'name' => 'حساب وسيط قديم',
            'username' => 'legacy-intermediary',
            'password' => 'secure-password',
            'account_type' => 'partner',
            'party_id' => $party->id,
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('workspaces.index'))
            ->assertRedirect(route('workspaces.enter', ['workspace' => 'marketer']));
        $this->actingAs($user)->get('/workspaces/intermediary')
            ->assertOk()->assertSee('منطقة المسوقين والوسطاء');
    }

    public function test_party_with_linked_login_cannot_lose_all_operational_roles(): void
    {
        $admin = $this->userWith(['parties.update']);
        $party = Party::query()->create(['entity_type' => 'individual', 'name' => 'مسوق مرتبط']);
        $party->partyRoles()->create(['role' => 'marketer']);
        User::query()->create([
            'name' => 'حساب المسوق',
            'username' => 'role-protected-account',
            'password' => 'long-secure-password',
            'account_type' => 'partner',
            'party_id' => $party->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->put(route('admin.parties.update', $party), [
            'entity_type' => 'individual',
            'name' => 'مسوق مرتبط',
            'roles' => ['supplier'],
        ])->assertStatus(422);

        $this->assertSame(['marketer'], $party->fresh()->assignedRoles());
    }

    public function test_login_is_blocked_when_linked_party_is_inactive(): void
    {
        $party = Party::query()->create([
            'entity_type' => 'individual',
            'name' => 'طرف موقوف',
            'is_active' => false,
        ]);
        $party->partyRoles()->create(['role' => 'marketer']);
        User::query()->create([
            'name' => 'حساب طرف موقوف',
            'username' => 'inactive-party-login',
            'password' => 'secure-password',
            'account_type' => 'partner',
            'party_id' => $party->id,
            'is_active' => true,
        ]);

        $this->post(route('login.store'), [
            'username' => 'inactive-party-login',
            'password' => 'secure-password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_scoped_manager_cannot_link_an_account_to_a_party_without_party_scope_support(): void
    {
        $admin = $this->userWith(['users.create'], false);
        $party = Party::query()->create(['entity_type' => 'individual', 'name' => 'مسوق بنطاق مستقل']);
        $party->partyRoles()->create(['role' => 'marketer']);

        $this->actingAs($admin)->post(route('admin.access.users.store'), [
            'name' => 'حساب مسوق محدود',
            'username' => 'scoped-marketer-login',
            'password' => 'long-secure-password',
            'password_confirmation' => 'long-secure-password',
            'party_id' => $party->id,
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['username' => 'scoped-marketer-login']);
    }


    public function test_party_directory_is_not_visible_to_a_user_without_global_party_scope(): void
    {
        $user = $this->userWith(['parties.view'], false);

        $this->actingAs($user)->get(route('admin.parties.index'))->assertForbidden();
    }


    public function test_permissions_are_denied_to_a_linked_account_after_its_party_is_deactivated(): void
    {
        app(DatabaseSeeder::class)->run();
        $party = Party::query()->create(['entity_type' => 'individual', 'name' => 'مسوق يتم إيقافه']);
        $party->partyRoles()->create(['role' => 'marketer']);
        $permission = Permission::query()->where('key', 'parties.view')->firstOrFail();
        $role = Role::query()->create(['name' => 'Partner access', 'key' => 'partner-access-test', 'is_active' => true]);
        $role->permissions()->sync([$permission->id]);
        $account = User::query()->create([
            'name' => 'حساب مسوق',
            'username' => 'deactivated-party-account',
            'password' => 'long-secure-password',
            'account_type' => 'partner',
            'party_id' => $party->id,
            'is_active' => true,
        ]);
        $account->roles()->attach($role);

        $this->assertTrue($account->hasPermission('parties.view'));
        $party->update(['is_active' => false]);
        $this->assertFalse($account->fresh()->hasPermission('parties.view'));
    }

    public function test_party_must_have_at_least_one_valid_role(): void
    {
        $user = $this->userWith(['parties.create']);
        $this->actingAs($user)->from('/dashboard')->post(route('admin.parties.store'), [
            'entity_type' => 'individual', 'name' => 'بدون صفة', 'roles' => [],
        ])->assertSessionHasErrors('roles');
    }
}
