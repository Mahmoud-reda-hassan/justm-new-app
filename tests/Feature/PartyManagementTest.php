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

    private function userWith(array $permissions): User
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
        $role->permissions()->sync(Permission::query()->whereIn('key', $permissions)->pluck('id'));
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
            'roles' => ['marketer', 'sales_intermediary'],
        ])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(['marketer', 'sales_intermediary'], $party->fresh()->assignedRoles());

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

    public function test_customer_party_cannot_be_linked_to_an_operational_login_account(): void
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
        ])->assertStatus(422);

        $this->assertDatabaseMissing('users', ['username' => 'customer-only-login']);
    }

    public function test_party_must_have_at_least_one_valid_role(): void
    {
        $user = $this->userWith(['parties.create']);
        $this->actingAs($user)->from('/dashboard')->post(route('admin.parties.store'), [
            'entity_type' => 'individual', 'name' => 'بدون صفة', 'roles' => [],
        ])->assertSessionHasErrors('roles');
    }
}
