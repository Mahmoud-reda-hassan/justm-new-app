<?php

namespace Tests\Feature;

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
}
