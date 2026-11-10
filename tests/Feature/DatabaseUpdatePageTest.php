<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseUpdatePageTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        return User::create([
            'name' => 'Database Update Tester',
            'username' => 'database-update-'.uniqid(),
            'password' => 'secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_database_update_page_is_available_to_access_admin(): void
    {
        $role = Role::query()->create(['name' => 'مدير الوصول', 'key' => 'system-access-admin', 'is_active' => true]);
        $user = $this->createUser();
        $user->roles()->attach($role);

        $this->actingAs($user)->get(route('admin.system.database-updates'))
            ->assertOk()
            ->assertSee('تحديث قاعدة البيانات');
    }

    public function test_database_update_page_uses_adminlte_cards_and_migration_table(): void
    {
        $role = Role::query()->create(['name' => 'مدير الوصول', 'key' => 'system-access-admin', 'is_active' => true]);
        $user = $this->createUser();
        $user->roles()->attach($role);

        $this->actingAs($user)->get(route('admin.system.database-updates'))
            ->assertOk()
            ->assertSee('card card-outline card-primary', false)
            ->assertSee('table table-bordered table-striped table-hover', false)
            ->assertSee('حالة تحديثات قاعدة البيانات');
    }

    public function test_database_update_page_is_forbidden_to_other_users(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->get(route('admin.system.database-updates'))->assertForbidden();
    }

    public function test_update_requires_exact_database_name_and_confirmation(): void
    {
        $role = Role::query()->create(['name' => 'مدير الوصول', 'key' => 'system-access-admin', 'is_active' => true]);
        $user = $this->createUser();
        $user->roles()->attach($role);

        $this->actingAs($user)->post(route('admin.system.database-updates.run'), [
            'database_confirmation' => 'wrong-database',
            'run_confirmation' => 'RUN MIGRATIONS',
        ])->assertSessionHasErrors('database_confirmation');
    }
}
