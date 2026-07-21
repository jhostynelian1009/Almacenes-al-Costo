<?php

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const NEUTRAL_STATUS = 'Si el correo corresponde a una cuenta habilitada, recibirás un enlace con los siguientes pasos.';

    private const OLD_PASSWORD = 'original-password';

    private const NEW_PASSWORD = 'new-secure-password';

    public function test_forgot_password_screen_is_rendered(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertViewIs('auth.forgot-password');
    }

    public function test_reset_password_screen_is_rendered_with_token_and_email(): void
    {
        $token = 'sample-reset-token';
        $email = 'employee@example.com';

        $this->get(route('password.reset', ['token' => $token, 'email' => $email]))
            ->assertOk()
            ->assertViewIs('auth.reset-password')
            ->assertViewHas('token', $token)
            ->assertViewHas('email', $email);
    }

    public function test_active_user_receives_the_native_reset_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'employee@example.com']);

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => '  EMPLOYEE@EXAMPLE.COM '])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', self::NEUTRAL_STATUS);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_unknown_email_receives_a_neutral_response_without_notification(): void
    {
        Notification::fake();

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'unknown@example.com'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', self::NEUTRAL_STATUS);

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_inactive_user_receives_a_neutral_response_without_notification(): void
    {
        Notification::fake();
        $user = User::factory()->inactive()->create(['email' => 'inactive@example.com']);

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', self::NEUTRAL_STATUS);

        Notification::assertNotSentTo($user, ResetPasswordNotification::class);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_recovery_responses_do_not_enumerate_accounts_or_broker_throttling(): void
    {
        Notification::fake();
        $activeUser = User::factory()->create(['email' => 'active@example.com']);
        $inactiveUser = User::factory()->inactive()->create(['email' => 'inactive@example.com']);

        $emails = [
            $activeUser->email,
            'unknown@example.com',
            $inactiveUser->email,
            $activeUser->email,
        ];

        foreach ($emails as $email) {
            $this->from(route('password.request'))
                ->post(route('password.email'), ['email' => $email])
                ->assertRedirect(route('password.request'))
                ->assertSessionHas('status', self::NEUTRAL_STATUS);
        }

        Notification::assertSentToTimes($activeUser, ResetPasswordNotification::class, 1);
        Notification::assertNotSentTo($inactiveUser, ResetPasswordNotification::class);
    }

    public function test_invalid_email_fails_validation_without_notification(): void
    {
        Notification::fake();

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'not-an-email'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_repeated_link_requests_are_limited_with_a_neutral_response(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'limited@example.com']);
        $ip = '203.0.113.10';
        $this->withServerVariables(['REMOTE_ADDR' => $ip]);

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->from(route('password.request'))
                ->post(route('password.email'), ['email' => $user->email])
                ->assertRedirect(route('password.request'))
                ->assertSessionHas('status', self::NEUTRAL_STATUS);
        }

        $request = ForgotPasswordRequest::create(
            '/forgot-password',
            'POST',
            ['email' => $user->email],
            [],
            [],
            ['REMOTE_ADDR' => $ip],
        );

        $this->assertSame(5, RateLimiter::attempts($request->throttleKey()));
        Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1);
    }

    public function test_rotating_emails_cannot_bypass_the_ip_rate_limit(): void
    {
        $ip = '203.0.113.20';

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $request = ForgotPasswordRequest::create(
                '/forgot-password',
                'POST',
                ['email' => "rotated-{$attempt}@example.com"],
                [],
                [],
                ['REMOTE_ADDR' => $ip],
            );

            $this->assertFalse($request->isRateLimited());
            $request->incrementRateLimiter();
        }

        $nextRequest = ForgotPasswordRequest::create(
            '/forgot-password',
            'POST',
            ['email' => 'another-address@example.com'],
            [],
            [],
            ['REMOTE_ADDR' => $ip],
        );

        $this->assertTrue($nextRequest->isRateLimited());
    }

    public function test_valid_token_resets_password_rotates_remember_token_and_emits_event(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => self::OLD_PASSWORD,
            'remember_token' => 'original-remember-token',
        ]);
        $originalRememberToken = $user->remember_token;
        $token = Password::createToken($user);
        Event::fake([PasswordReset::class]);

        $this->post(route('password.update'), $this->resetPayload($user, $token))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $user->refresh();
        $this->assertGuest();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->password));
        $this->assertFalse(Hash::check(self::OLD_PASSWORD, $user->password));
        $this->assertNotSame($originalRememberToken, $user->remember_token);
        $this->assertFalse(Password::tokenExists($user, $token));
        Event::assertDispatched(PasswordReset::class, fn (PasswordReset $event) => $event->user->is($user));

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => self::OLD_PASSWORD,
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
        ])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_token_is_rejected_without_exposing_it(): void
    {
        $user = User::factory()->create(['password' => self::OLD_PASSWORD]);
        $invalidToken = 'invalid-raw-token';
        Event::fake([PasswordReset::class]);

        $this->post(route('password.update'), $this->resetPayload($user, $invalidToken))
            ->assertSessionHasErrors('email');

        $this->assertStringNotContainsString($invalidToken, session('errors')->first('email'));
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->fresh()->password));
        Event::assertNotDispatched(PasswordReset::class);
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create(['password' => self::OLD_PASSWORD]);
        $token = Password::createToken($user);
        Event::fake([PasswordReset::class]);

        $this->travel((int) config('auth.passwords.users.expire') + 1)->minutes();

        $this->post(route('password.update'), $this->resetPayload($user, $token))
            ->assertSessionHasErrors('email');

        $this->travelBack();
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->fresh()->password));
        Event::assertNotDispatched(PasswordReset::class);
    }

    public function test_used_token_cannot_be_reused(): void
    {
        $user = User::factory()->create(['password' => self::OLD_PASSWORD]);
        $token = Password::createToken($user);
        Event::fake([PasswordReset::class]);

        $this->post(route('password.update'), $this->resetPayload($user, $token))
            ->assertRedirect(route('login'));

        $secondPassword = 'second-secure-password';
        $this->post(route('password.update'), $this->resetPayload($user, $token, $secondPassword))
            ->assertSessionHasErrors('email');

        $user->refresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->password));
        $this->assertFalse(Hash::check($secondPassword, $user->password));
        Event::assertDispatchedTimes(PasswordReset::class, 1);
    }

    public function test_token_cannot_be_used_with_another_email(): void
    {
        $tokenOwner = User::factory()->create(['password' => self::OLD_PASSWORD]);
        $otherUser = User::factory()->create(['password' => self::OLD_PASSWORD]);
        $token = Password::createToken($tokenOwner);
        Event::fake([PasswordReset::class]);

        $this->post(route('password.update'), $this->resetPayload($otherUser, $token))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $tokenOwner->fresh()->password));
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $otherUser->fresh()->password));
        Event::assertNotDispatched(PasswordReset::class);
    }

    public function test_inactive_user_cannot_use_a_previously_issued_token(): void
    {
        $user = User::factory()->create(['password' => self::OLD_PASSWORD]);
        $token = Password::createToken($user);
        $user->is_active = false;
        $user->save();
        Event::fake([PasswordReset::class]);

        $this->post(route('password.update'), $this->resetPayload($user, $token))
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $user->fresh()->password));
        Event::assertNotDispatched(PasswordReset::class);
    }

    public function test_password_confirmation_is_required(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $payload = $this->resetPayload($user, $token);
        unset($payload['password_confirmation']);

        $this->post(route('password.update'), $payload)
            ->assertSessionHasErrors(['password', 'password_confirmation']);

        $this->assertTrue(Password::tokenExists($user, $token));
    }

    public function test_password_shorter_than_twelve_characters_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post(route('password.update'), $this->resetPayload($user, $token, 'short-pass1'))
            ->assertSessionHasErrors('password');

        $this->assertTrue(Password::tokenExists($user, $token));
    }

    public function test_authenticated_user_cannot_access_password_recovery_routes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('password.request'))->assertRedirect(route('admin.dashboard'));
        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect(route('admin.dashboard'));
        $this->get(route('password.reset', ['token' => 'token', 'email' => $user->email]))
            ->assertRedirect(route('admin.dashboard'));
        $this->post(route('password.update'), $this->resetPayload($user, 'token'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_public_registration_routes_remain_absent(): void
    {
        $this->assertFalse(Route::has('register'));
        $this->get('/register')->assertNotFound();
    }

    /**
     * @return array<string, string>
     */
    private function resetPayload(User $user, string $token, string $password = self::NEW_PASSWORD): array
    {
        return [
            'token' => $token,
            'email' => $user->email,
            'password' => $password,
            'password_confirmation' => $password,
        ];
    }
}
