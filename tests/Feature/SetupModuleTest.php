<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_command_runs_migrations_and_seeds_the_limited_access_role(): void
    {
        $this->artisan('justm:setup')
            ->expectsOutputToContain('اكتمل إنشاء الجداول وإدخال البيانات الأولية.')
            ->assertExitCode(0);

        $role = Role::query()->where('key', 'system-access-admin')->firstOrFail();

        $this->assertSame(11, $role->permissions()->count());
        $this->assertSame(0, User::query()->count());
    }

    public function test_setup_seeding_is_repeatable_without_creating_duplicate_roles(): void
    {
        $this->seed();

        $this->artisan('db:seed')->assertExitCode(0);

        $this->assertSame(1, Role::query()->where('key', 'system-access-admin')->count());
    }

    public function test_first_admin_can_be_created_once_with_only_setup_permissions(): void
    {
        $this->seed();

        $this->artisan('justm:provision-first-admin', [
            '--name' => 'First Admin',
            '--username' => 'first-admin',
            '--email' => 'first@example.test',
        ])
            ->expectsQuestion('كلمة المرور (12 حرفًا على الأقل)', 'correct-horse-battery')
            ->expectsQuestion('تأكيد كلمة المرور', 'correct-horse-battery')
            ->expectsOutputToContain('تم إنشاء أول مدير بنجاح.')
            ->assertExitCode(0);

        $user = User::query()->where('username', 'first-admin')->firstOrFail();

        $this->assertTrue(password_verify('correct-horse-battery', $user->password));
        $this->assertSame(['system-access-admin'], $user->roles()->pluck('key')->all());
        $this->assertSame(11, $user->roles()->first()->permissions()->count());
    }

    public function test_first_admin_provisioning_is_refused_when_any_user_exists(): void
    {
        $this->seed();
        User::create([
            'name' => 'Existing User',
            'username' => 'existing-user',
            'password' => 'existing-secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);

        $this->artisan('justm:provision-first-admin', [
            '--name' => 'Another Admin',
            '--username' => 'another-admin',
        ])
            ->expectsOutputToContain('تم رفض العملية: يوجد مستخدم بالفعل.')
            ->assertExitCode(1);

        $this->assertSame(1, User::query()->count());
    }
}
