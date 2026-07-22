<?php

namespace Tests\Feature;

use App\Http\Controllers\PublicCategoryController;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_category_route_keeps_its_contract_and_is_available_to_visitors(): void
    {
        $route = Route::getRoutes()->getByName('categories.index');

        $this->assertNotNull($route);
        $this->assertSame('categorias', $route->uri());
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertSame(PublicCategoryController::class.'@index', $route->getActionName());

        $this->get('/categorias')
            ->assertOk()
            ->assertViewIs('public.categories.index')
            ->assertSeeInOrder(['aria-current="page"', 'Categorías'], false);
    }

    public function test_authenticated_users_can_access_public_categories(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('categories.index'))
            ->assertOk()
            ->assertSee('Categorías');
    }

    public function test_active_categories_render_their_complete_public_hierarchy(): void
    {
        $root = $this->category([
            'name' => 'Raíz pública',
            'description' => 'Descripción de la raíz.',
        ]);
        $child = $this->category([
            'name' => 'Subcategoría pública',
            'parent_id' => $root->id,
        ]);
        $this->category([
            'name' => 'Tercer nivel público',
            'parent_id' => $child->id,
        ]);

        $this->get(route('categories.index'))
            ->assertOk()
            ->assertSeeInOrder(['Raíz pública', 'Subcategoría pública', 'Tercer nivel público'])
            ->assertSee('Descripción de la raíz.')
            ->assertSee('Nivel 1')
            ->assertSee('Nivel 2')
            ->assertSee('Nivel 3')
            ->assertSee('aria-label="Subcategorías de Raíz pública"', false);
    }

    public function test_inactive_category_and_its_active_descendants_are_hidden_without_promotion(): void
    {
        $inactiveRoot = $this->category([
            'name' => 'Raíz inactiva oculta',
            'is_active' => false,
        ]);
        $activeChild = $this->category([
            'name' => 'Descendiente activo oculto',
            'parent_id' => $inactiveRoot->id,
        ]);
        $this->category([
            'name' => 'Nieto activo oculto',
            'parent_id' => $activeChild->id,
        ]);
        $this->category(['name' => 'Rama pública válida']);

        $this->get(route('categories.index'))
            ->assertOk()
            ->assertSee('Rama pública válida')
            ->assertDontSee('Raíz inactiva oculta')
            ->assertDontSee('Descendiente activo oculto')
            ->assertDontSee('Nieto activo oculto');
    }

    public function test_inactive_intermediate_ancestor_hides_only_its_dependent_subtree(): void
    {
        $root = $this->category(['name' => 'Raíz activa visible']);
        $visibleChild = $this->category([
            'name' => 'Rama hermana visible',
            'parent_id' => $root->id,
        ]);
        $inactiveChild = $this->category([
            'name' => 'Intermedia inactiva',
            'parent_id' => $root->id,
            'is_active' => false,
        ]);
        $this->category([
            'name' => 'Descendiente de intermedia oculto',
            'parent_id' => $inactiveChild->id,
        ]);

        $this->get(route('categories.index'))
            ->assertOk()
            ->assertSeeInOrder([$root->name, $visibleChild->name])
            ->assertDontSee('Intermedia inactiva')
            ->assertDontSee('Descendiente de intermedia oculto');
    }

    public function test_categories_are_ordered_by_display_order_and_then_name_at_each_level(): void
    {
        $secondRoot = $this->category([
            'name' => 'Raíz segunda',
            'display_order' => 2,
        ]);
        $firstRoot = $this->category([
            'name' => 'Raíz primera',
            'display_order' => 1,
        ]);

        $this->category([
            'name' => 'Hija Beta',
            'parent_id' => $firstRoot->id,
            'display_order' => 1,
        ]);
        $this->category([
            'name' => 'Hija Alfa',
            'parent_id' => $firstRoot->id,
            'display_order' => 1,
        ]);
        $this->category([
            'name' => 'Hija por orden',
            'parent_id' => $firstRoot->id,
            'display_order' => 0,
        ]);
        $this->category([
            'name' => 'Hija de segunda raíz',
            'parent_id' => $secondRoot->id,
            'display_order' => 0,
        ]);

        $this->get(route('categories.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'Raíz primera',
                'Hija por orden',
                'Hija Alfa',
                'Hija Beta',
                'Raíz segunda',
                'Hija de segunda raíz',
            ]);
    }

    public function test_public_categories_are_loaded_with_one_category_query(): void
    {
        $root = $this->category(['name' => 'Raíz para consulta única']);
        $child = $this->category([
            'name' => 'Hija para consulta única',
            'parent_id' => $root->id,
        ]);
        $this->category([
            'name' => 'Nieta para consulta única',
            'parent_id' => $child->id,
        ]);

        $categoryQueries = 0;

        DB::listen(function (QueryExecuted $query) use (&$categoryQueries): void {
            if (preg_match('/\bfrom\s+[`"]?categories[`"]?/i', $query->sql) === 1) {
                $categoryQueries++;
            }
        });

        $this->get(route('categories.index'))->assertOk();

        $this->assertSame(1, $categoryQueries);
    }

    public function test_public_output_excludes_image_icon_and_administrative_fields(): void
    {
        $category = $this->category([
            'name' => 'Categoría con datos internos',
            'slug' => 'slug-interno-no-publico',
            'description' => 'Descripción pública permitida.',
            'image' => 'imagen-interna-no-publica.webp',
            'icon' => 'icono-interno-no-publico.svg',
            'display_order' => 8127,
        ]);

        $this->get(route('categories.index'))
            ->assertOk()
            ->assertSee($category->name)
            ->assertSee('Descripción pública permitida.')
            ->assertDontSee('slug-interno-no-publico')
            ->assertDontSee('imagen-interna-no-publica.webp')
            ->assertDontSee('icono-interno-no-publico.svg')
            ->assertDontSee('8127')
            ->assertDontSee('Editar')
            ->assertDontSee('Eliminar');
    }

    public function test_empty_state_uses_the_public_layout_navigation_and_footer(): void
    {
        $this->get(route('categories.index'))
            ->assertOk()
            ->assertViewIs('public.categories.index')
            ->assertSee('data-empty-state="public-categories"', false)
            ->assertSee('No hay categorías disponibles')
            ->assertSee('aria-label="Navegación principal"', false)
            ->assertSee('id="main-content"', false)
            ->assertSee('Todos los derechos reservados');
    }

    public function test_category_page_does_not_create_detail_links_or_routes(): void
    {
        $category = $this->category(['name' => 'Categoría sin enlace']);

        $response = $this->get(route('categories.index'))->assertOk();

        $response->assertDontSee('/categorias/'.$category->id, false);
        $this->assertFalse(Route::has('categories.show'));

        preg_match_all('/<a\b[^>]*href="([^"]+)"/i', $response->getContent(), $matches);

        $internalLinks = collect($matches[1])
            ->filter(fn (string $href): bool => str_starts_with($href, url('/')))
            ->map(fn (string $href): string => parse_url($href, PHP_URL_PATH) ?: '/')
            ->unique();

        foreach ($internalLinks as $path) {
            $this->get($path)->assertSuccessful();
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function category(array $attributes = []): Category
    {
        return Category::factory()->create($attributes);
    }
}
