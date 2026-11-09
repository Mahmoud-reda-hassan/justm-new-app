<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.setup_token' => str_repeat('s', 40)]);
    }

    public function test_setup_page_requires_a_valid_temporary_token_before_actions(): void
    {
        $this->get('/setup')->assertOk()->assertSee('مفتاح التثبيت');

        $this->post('/setup/access', ['setup_token' => 'wrong-token'])->assertSessionHasErrors('setup_token');
        $this->post('/setup/connection')->assertForbidden();

        $this->post('/setup/access', ['setup_token' => str_repeat('s', 40)])
            ->assertRedirect(route('setup.show'));

        $this->withSession(['justm_setup_authorized_at' => now()->timestamp])
            ->post('/setup/connection')
            ->assertSessionHas('setup_status');
    }

    public function test_database_setup_runs_migrations_and_initial_seeds_after_authorization(): void
    {
        $this->withSession(['justm_setup_authorized_at' => now()->timestamp])
            ->post('/setup/database')
            ->assertSessionHas('setup_status');

        $this->assertDatabaseHas('roles', ['key' => 'system-access-admin']);
    }

    public function test_first_admin_is_created_with_limited_role_and_password_is_hashed(): void
    {
        $this->withSession(['justm_setup_authorized_at' => now()->timestamp])
            ->post('/setup/database');

        $this->withSession(['justm_setup_authorized_at' => now()->timestamp])
            ->post('/setup/admin', [
                'name' => 'مدير النظام',
                'username' => 'first-admin',
                'email' => 'admin@example.test',
                'password' => 'secure-password-123',
                'password_confirmation' => 'secure-password-123',
            ])
            ->assertRedirect(route('login'));

        $user = User::query()->where('username', 'first-admin')->firstOrFail();
        $this->assertTrue(password_verify('secure-password-123', $user->password));
        $this->assertSame(['system-access-admin'], $user->roles()->pluck('key')->all());
        $this->assertGuest();
    }

    public function test_setup_is_locked_after_first_user_is_created(): void
    {
        User::create([
            'name' => 'Existing',
            'username' => 'existing-user',
            'password' => 'secure-password-123',
            'account_type' => 'admin',
            'is_active' => true,
        ]);

        $this->get('/setup')->assertNotFound();
        $this->withSession(['justm_setup_authorized_at' => now()->timestamp])
            ->post('/setup/admin', [])
            ->assertNotFound();
    }

    public function test_setup_authorization_expires_after_fifteen_minutes(): void
    {
        $this->withSession(['justm_setup_authorized_at' => now()->subMinutes(16)->timestamp])
            ->post('/setup/connection')
            ->assertForbidden();
    }
}
