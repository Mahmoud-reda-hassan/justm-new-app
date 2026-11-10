<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_verification_link_confirms_the_email_address(): void
    {
        $user = $this->makeUser();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(30),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );

        $this->get($url)
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_invalid_verification_signature_is_rejected(): void
    {
        $user = $this->makeUser();

        $this->get(route('verification.verify', [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]))->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_password_reset_link_is_not_sent_to_an_unverified_email(): void
    {
        Notification::fake();
        $this->makeUser();

        $this->post(route('password.email'), ['email' => 'admin@example.test'])
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_verified_active_user_can_reset_password(): void
    {
        Notification::fake();
        $user = $this->makeUser();
        $user->markEmailAsVerified();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);

        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secure-password-123',
            'password_confirmation' => 'new-secure-password-123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(password_verify('new-secure-password-123', $user->fresh()->password));
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'System Admin',
            'username' => 'system-admin',
            'email' => 'admin@example.test',
            'password' => 'old-secure-password',
            'account_type' => 'admin',
            'is_active' => true,
        ]);
    }
}
