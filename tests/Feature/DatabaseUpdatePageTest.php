<?php

namespace Tests\\Feature;

use App\\Models\\Role;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class DatabaseUpdatePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_update_page_is_available_to_access_admin(): void
    {
        $role = Role::query()->create(['name' => 'مدير الوصول', 'key' => 'system-access-admin', 'is_active' => true]);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role);

        $this->actingAs($user)->get(route('admin.system.database-updates'))
            ->assertOk()
            ->assertSee('تحديث قاعدة البيانات');
    }

    public function test_database_update_page_is_forbidden_to_other_users(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get(route('admin.system.database-updates'))->assertForbidden();
    }

    public function test_update_requires_exact_database_name_and_confirmation(): void
    {
        $role = Role::query()->create(['name' => 'مدير الوصول', 'key' => 'system-access-admin', 'is_active' => true]);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role);

        $this->actingAs($user)->post(route('admin.system.database-updates.run'), [
            'database_confirmation' => 'wrong-database',
            'run_confirmation' => 'RUN MIGRATIONS',
        ])->assertSessionHasErrors('database_confirmation');
    }
}
