<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
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
            ->assertViewHas('featuredBanners', fn ($banners) => $banners->isNotEmpty())
            ->assertViewHas('featuredProducts')
            ->assertSee('aria-label="Navegación principal"', false)
            ->assertSee('id="main-content"', false)
            ->assertSee('Almacenes al Costo')
            ->assertSee('Todos los derechos reservados');
    }

    public function test_home_renders_only_the_documented_featured_sections(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-home-section="featured-banners"', false)
            ->assertSee('data-home-section="featured-products"', false)
            ->assertSee('Productos destacados')
            ->assertDontSee('data-home-section="categories"', false)
            ->assertDontSee('data-home-section="promotions"', false);
    }

    public function test_home_uses_empty_states_when_featured_data_does_not_exist(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Precios al costo en todo el catálogo')
            ->assertSee('data-empty-state="featured-products"', false)
            ->assertSee('No hay productos destacados disponibles');
    }

    public function test_featured_components_keep_images_and_carousel_controls_accessible(): void
    {
        $carousel = Blade::render('<x-public.featured-carousel :banners="$banners" />', [
            'banners' => [
                [
                    'image_url' => '/images/example.webp',
                    'image_alt' => 'Descripción alternativa del banner',
                    'title' => 'Contenido destacado',
                    'message' => null,
                    'cta_url' => null,
                    'cta_label' => null,
                ],
                [
                    'image_url' => '/images/example-2.webp',
                    'image_alt' => 'Descripción alternativa del segundo banner',
                    'title' => 'Segundo contenido destacado',
                    'message' => null,
                    'cta_url' => null,
                    'cta_label' => null,
                ],
            ],
        ]);

        $this->assertStringContainsString('class="d-block w-100 home-featured-carousel__image"', $carousel);
        $this->assertStringContainsString('alt="Descripción alternativa del banner"', $carousel);
        $this->assertStringContainsString('Banner anterior', $carousel);
        $this->assertStringContainsString('Banner siguiente', $carousel);
        $this->assertStringNotContainsString('data-bs-ride=', $carousel);

        $productCard = Blade::render(
            '<x-public.product-card name="Elemento de catálogo" image-url="/images/example.webp" image-alt="Descripción alternativa del producto" detail-url="/product/ejemplo" url="/product/ejemplo" />'
        );

        $this->assertStringContainsString('class="card-img-top img-fluid product-card__image"', $productCard);
        $this->assertStringContainsString('alt="Descripción alternativa del producto"', $productCard);
        $this->assertStringContainsString('loading="lazy"', $productCard);
        $this->assertStringContainsString('href="/product/ejemplo"', $productCard);
    }

    public function test_home_calls_to_action_target_the_existing_catalog_route(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-home-cta="catalog"', false)
            ->assertSee('data-home-cta="featured-products"', false)
            ->assertSee('href="'.route('catalog.index').'"', false);

        $this->get(route('catalog.index'))->assertOk();
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

    public function test_home_does_not_contain_broken_internal_links(): void
    {
        $response = $this->get(route('home'))->assertOk();

        preg_match_all('/<a\b[^>]*href="([^"]+)"/i', $response->getContent(), $matches);

        $internalLinks = collect($matches[1])
            ->filter(fn (string $href): bool => str_starts_with($href, url('/')))
            ->map(fn (string $href): string => parse_url($href, PHP_URL_PATH) ?: '/')
            ->unique();

        $this->assertNotEmpty($internalLinks);

        foreach ($internalLinks as $path) {
            $this->get($path)->assertSuccessful();
        }
    }

    public function test_routes_outside_the_public_home_scope_were_not_added(): void
    {
        foreach (['catalog.search', 'products.show', 'cart.index', 'checkout.index'] as $routeName) {
            $this->assertFalse(Route::has($routeName));
        }

        $this->assertTrue(Route::has('catalog.show'));
    }
}
