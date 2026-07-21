<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    public static function publicRoutes(): array
    {
        return [
            'home' => 'Inicio',
            'catalog.index' => 'Catálogo',
            'categories.index' => 'Categorías',
            'promotions.index' => 'Promociones',
            'information' => 'Información',
        ];
    }

    public function test_home_renders_the_public_layout_navigation_and_footer(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertViewIs('public.home')
            ->assertSee('aria-label="Navegación principal"', false)
            ->assertSee('id="main-content"', false)
            ->assertSee('Almacenes al Costo')
            ->assertSee('Todos los derechos reservados');
    }

    public function test_all_public_navigation_routes_are_named_and_accessible_to_visitors(): void
    {
        foreach (self::publicRoutes() as $routeName => $label) {
            $this->assertTrue(Route::has($routeName));
            $this->get(route($routeName))
                ->assertOk()
                ->assertSee($label);
        }
    }

    public function test_primary_navigation_contains_working_named_links(): void
    {
        $response = $this->get(route('home'));

        foreach (array_keys(self::publicRoutes()) as $routeName) {
            $response->assertSee('href="'.route($routeName).'"', false);
        }

        $response
            ->assertSee('data-bs-toggle="collapse"', false)
            ->assertSee('aria-controls="publicNavigation"', false)
            ->assertSee('aria-expanded="false"', false);
    }

    public function test_current_public_route_is_identified_in_the_navigation(): void
    {
        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('aria-current="page"', false)
            ->assertSeeInOrder(['aria-current="page"', 'Catálogo'], false);
    }

    public function test_authenticated_internal_user_can_access_the_public_site(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Panel interno');
    }

    public function test_existing_authentication_routes_remain_available(): void
    {
        foreach (['login', 'login.store', 'password.request', 'password.email', 'password.reset', 'password.update', 'logout'] as $routeName) {
            $this->assertTrue(Route::has($routeName));
        }

        $this->get(route('login'))->assertOk();
        $this->get(route('password.request'))->assertOk();
    }

    public function test_administration_remains_protected_and_public_registration_is_absent(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertFalse(Route::has('register'));
        $this->get('/register')->assertNotFound();
    }
}
