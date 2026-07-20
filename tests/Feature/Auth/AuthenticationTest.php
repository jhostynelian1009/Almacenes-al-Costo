<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Database\Seeders\FirstAdminSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const INVALID_CREDENTIALS = 'Las credenciales proporcionadas no son correctas.';

    public function test_login_screen_is_rendered(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertViewIs('auth.login')
            ->assertSee('Iniciar sesión');
    }

    public function test_active_user_can_log_in_and_email_is_normalized(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@example.com',
            'password' => 'a-secure-password',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => '  EMPLOYEE@EXAMPLE.COM ',
            'password' => 'a-secure-password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_session_is_regenerated_after_login(): void
    {
        $user = User::factory()->create(['password' => 'a-secure-password']);
        $this->get(route('login'));
        $previousSessionId = $this->app['session']->getId();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'a-secure-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertNotSame($previousSessionId, $this->app['session']->getId());
    }

    public function test_invalid_credentials_are_rejected_with_generic_message(): void
    {
        User::factory()->create(['email' => 'known@example.com']);

        $this->post(route('login.store'), [
            'email' => 'known@example.com',
            'password' => 'incorrect-password',
        ])->assertSessionHasErrors(['email' => self::INVALID_CREDENTIALS]);

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create(['password' => 'a-secure-password']);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'a-secure-password',
        ])->assertSessionHasErrors(['email' => self::INVALID_CREDENTIALS]);

        $this->assertGuest();
    }

    public function test_user_can_log_out_only_with_post(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->get('/logout')->assertStatus(405);
    }

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_admin_and_employee_can_access_dashboard(): void
    {
        foreach ([UserRole::Admin, UserRole::Employee] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get(route('admin.dashboard'))
                ->assertOk();

            $this->post(route('logout'));
        }
    }

    public function test_existing_session_is_ended_when_user_becomes_inactive(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->is_active = false;
        $user->save();

        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_repeated_attempts_trigger_rate_limiting(): void
    {
        $email = 'limited@example.com';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), [
                'email' => $email,
                'password' => 'incorrect-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('login.store'), [
            'email' => $email,
            'password' => 'incorrect-password',
        ])->assertSessionHasErrors('email');

        $this->assertStringContainsString('Demasiados intentos', session('errors')->first('email'));
    }

    public function test_successful_login_clears_attempt_counter(): void
    {
        $user = User::factory()->create([
            'email' => 'clear-counter@example.com',
            'password' => 'a-secure-password',
        ]);

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'incorrect-password',
            ]);
        }

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'a-secure-password',
        ])->assertRedirect(route('admin.dashboard'));
        $this->post(route('logout'));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $response = $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'incorrect-password',
            ]);
        }

        $response->assertSessionHasErrors(['email' => self::INVALID_CREDENTIALS]);
    }

    public function test_rate_limit_key_has_fixed_length_and_does_not_expose_email(): void
    {
        $request = LoginRequest::create('/login', 'POST', [
            'email' => 'PRIVATE.USER@EXAMPLE.COM',
        ]);
        $request->server->set('REMOTE_ADDR', '2001:db8::1');

        $key = $request->throttleKey();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $key);
        $this->assertStringNotContainsString('private.user@example.com', $key);
    }

    public function test_roles_are_cast_and_invalid_database_values_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();

        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertSame(UserRole::Employee, $employee->role);

        $this->expectException(QueryException::class);
        DB::table('users')->insert([
            'name' => 'Invalid Role',
            'email' => 'invalid-role@example.com',
            'password' => Hash::make('a-secure-password'),
            'role' => 'owner',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_first_admin_seeder_hashes_password_and_is_repeatable(): void
    {
        config()->set('auth.initial_admin', [
            'name' => 'Initial Admin',
            'email' => 'ADMIN@EXAMPLE.COM',
            'password' => 'a-strong-initial-password',
        ]);

        $this->seed(FirstAdminSeeder::class);
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $originalHash = $admin->password;

        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertNotSame('a-strong-initial-password', $admin->password);
        $this->assertTrue(Hash::check('a-strong-initial-password', $admin->password));

        $this->seed(FirstAdminSeeder::class);
        $this->assertSame(1, User::where('email', 'admin@example.com')->count());
        $this->assertSame($originalHash, $admin->fresh()->password);
    }

    public function test_first_admin_is_skipped_when_configuration_is_missing(): void
    {
        config()->set('auth.initial_admin', ['name' => null, 'email' => null, 'password' => null]);

        $this->seed(FirstAdminSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_first_admin_seeder_rejects_an_existing_non_admin_account(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);
        config()->set('auth.initial_admin', [
            'name' => 'Initial Admin',
            'email' => $user->email,
            'password' => 'a-strong-initial-password',
        ]);

        try {
            $this->seed(FirstAdminSeeder::class);
            $this->fail('The seeder should reject a conflicting non-admin account.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('no es un administrador activo', $exception->getMessage());
        }

        $this->assertSame(UserRole::Employee, $user->fresh()->role);
    }

    public function test_public_registration_routes_do_not_exist(): void
    {
        $this->assertFalse(Route::has('register'));
        $this->get('/register')->assertNotFound();
    }
}
