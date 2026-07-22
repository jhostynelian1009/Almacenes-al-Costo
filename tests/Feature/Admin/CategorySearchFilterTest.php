<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CategorySearchFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_partial_category_names_without_case_sensitivity_when_supported(): void
    {
        $admin = User::factory()->admin()->create();
        $match = Category::factory()->create(['name' => 'Audio para el Hogar']);
        Category::factory()->create(['name' => 'Muebles de Oficina']);

        $this->actingAs($admin)
            ->get(route('admin.categories.index', ['search' => 'audio para']))
            ->assertOk()
            ->assertViewHas('categories', fn (LengthAwarePaginator $categories): bool => $categories->count() === 1
                && $categories->first()->is($match));
    }

    public function test_search_does_not_return_nonmatching_names_or_search_other_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $match = Category::factory()->create(['name' => 'Decoración Especial']);
        Category::factory()->create([
            'name' => 'Nombre diferente',
            'slug' => 'decoracion-en-slug',
            'description' => 'Decoración en descripción',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.categories.index', ['search' => 'Decoración']))
            ->assertOk()
            ->assertViewHas('categories', fn (LengthAwarePaginator $categories): bool => $categories->count() === 1
                && $categories->first()->is($match));
    }

    public function test_search_spaces_are_normalized(): void
    {
        $admin = User::factory()->admin()->create();
        $match = Category::factory()->create(['name' => 'Audio para Casa']);

        $this->actingAs($admin)
            ->get(route('admin.categories.index', ['search' => '  Audio   para  ']))
            ->assertOk()
            ->assertViewHas('filters', fn (array $filters): bool => $filters['search'] === 'Audio para')
            ->assertViewHas('categories', fn (LengthAwarePaginator $categories): bool => $categories->first()->is($match));
    }

    public function test_zero_is_treated_as_a_valid_search_term(): void
    {
        $admin = User::factory()->admin()->create();
        $match = Category::factory()->create(['name' => 'Serie 0']);
        Category::factory()->create(['name' => 'Serie especial']);

        $this->actingAs($admin)
            ->get(route('admin.categories.index', ['search' => '0']))
            ->assertViewHas('categories', fn (LengthAwarePaginator $categories): bool => $categories->count() === 1
                && $categories->first()->is($match));
    }

    public function test_admin_can_filter_active_and_inactive_categories(): void
    {
        $admin = User::factory()->admin()->create();
        $active = Category::factory()->active()->create();
        $inactive = Category::factory()->inactive()->create();

        $this->actingAs($admin)
            ->get(route('admin.categories.index', ['status' => 'active']))
            ->assertViewHas('categories', fn (LengthAwarePaginator $categories): bool => $categories->count() === 1
                && $categories->first()->is($active));

        $this->get(route('admin.categories.index', ['status' => 'inactive']))
            ->assertViewHas('categories', fn (LengthAwarePaginator $categories): bool => $categories->count() === 1
                && $categories->first()->is($inactive));
    }

    public function test_invalid_status_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.categories.index'))
            ->get(route('admin.categories.index', ['status' => 'archived']))
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHasErrors('status');
    }

    public function test_admin_can_filter_by_parent_category(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->create();
        $otherParent = Category::factory()->create();
        $match = Category::factory()->childOf($parent)->create();
        Category::factory()->childOf($otherParent)->create();

        $this->actingAs($admin)
            ->get(route('admin.categories.index', ['parent_id' => $parent->getKey()]))
            ->assertViewHas('categories', fn (LengthAwarePaginator $categories): bool => $categories->count() === 1
                && $categories->first()->is($match));
    }

    public function test_nonexistent_parent_id_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.categories.index', ['parent_id' => 999999]))
            ->assertSessionHasErrors('parent_id');
    }

    public function test_main_and_child_level_filters_use_parent_presence(): void
    {
        $admin = User::factory()->admin()->create();
        $main = Category::factory()->main()->create();
        $child = Category::factory()->childOf($main)->create();

        $this->actingAs($admin)
            ->get(route('admin.categories.index', ['level' => 'main']))
            ->assertViewHas('categories', fn (LengthAwarePaginator $categories): bool => $categories->count() === 1
                && $categories->first()->is($main));

        $this->get(route('admin.categories.index', ['level' => 'child']))
            ->assertViewHas('categories', fn (LengthAwarePaginator $categories): bool => $categories->count() === 1
                && $categories->first()->is($child));
    }

    public function test_invalid_hierarchy_level_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.categories.index', ['level' => 'third']))
            ->assertSessionHasErrors('level');
    }

    public function test_search_status_parent_and_level_filters_can_be_combined(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->create();
        $otherParent = Category::factory()->create();
        $match = Category::factory()->childOf($parent)->active()->create(['name' => 'Audio Especial']);
        Category::factory()->childOf($parent)->inactive()->create(['name' => 'Audio Inactivo']);
        Category::factory()->childOf($otherParent)->active()->create(['name' => 'Audio Otro']);
        Category::factory()->childOf($parent)->active()->create(['name' => 'Mueble Especial']);

        $this->actingAs($admin)
            ->get(route('admin.categories.index', [
                'search' => 'Audio',
                'status' => 'active',
                'parent_id' => $parent->getKey(),
                'level' => 'child',
            ]))
            ->assertViewHas('categories', fn (LengthAwarePaginator $categories): bool => $categories->count() === 1
                && $categories->first()->is($match));
    }

    public function test_filtered_results_remain_ordered_by_display_order_and_name(): void
    {
        $admin = User::factory()->admin()->create();
        Category::factory()->active()->create(['name' => 'Zulu filtro', 'display_order' => 2]);
        Category::factory()->active()->create(['name' => 'Beta filtro', 'display_order' => 1]);
        Category::factory()->active()->create(['name' => 'Alfa filtro', 'display_order' => 1]);
        Category::factory()->inactive()->create(['name' => 'Primera excluida', 'display_order' => 0]);

        $this->actingAs($admin)
            ->get(route('admin.categories.index', ['status' => 'active']))
            ->assertViewHas('categories', fn (LengthAwarePaginator $categories): bool => $categories->pluck('name')->all() === [
                'Alfa filtro',
                'Beta filtro',
                'Zulu filtro',
            ]);
    }

    public function test_index_uses_fixed_fifteen_item_pagination(): void
    {
        $admin = User::factory()->admin()->create();
        Category::factory()->count(16)->create();

        $this->actingAs($admin)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertViewHas('categories', fn (LengthAwarePaginator $categories): bool => $categories->perPage() === 15
                && $categories->count() === 15
                && $categories->total() === 16
                && $categories->lastPage() === 2);
    }

    public function test_pagination_links_preserve_validated_query_parameters(): void
    {
        $admin = User::factory()->admin()->create();
        Category::factory()->count(16)->active()->create([
            'name' => fn (array $attributes): string => 'Filtro '.fake()->unique()->numerify('####'),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.categories.index', ['search' => 'Filtro', 'status' => 'active']))
            ->assertViewHas('categories', function (LengthAwarePaginator $categories): bool {
                parse_str((string) parse_url($categories->url(2), PHP_URL_QUERY), $query);

                return $query['search'] === 'Filtro'
                    && $query['status'] === 'active'
                    && $query['page'] === '2';
            });
    }

    public function test_view_preserves_filter_values_and_offers_a_clean_url(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->create(['name' => 'Padre seleccionable']);

        $this->actingAs($admin)
            ->get(route('admin.categories.index', [
                'search' => 'Padre',
                'status' => 'active',
                'parent_id' => $parent->getKey(),
                'level' => 'child',
            ]))
            ->assertOk()
            ->assertSee('value="Padre"', false)
            ->assertSee('value="active" selected', false)
            ->assertSee('value="'.$parent->getKey().'" selected', false)
            ->assertSee('value="child" selected', false)
            ->assertSee('href="'.route('admin.categories.index').'"', false)
            ->assertSee('Limpiar filtros');
    }

    public function test_initial_and_filtered_empty_states_are_different(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('No hay categorías registradas')
            ->assertDontSee('No hay categorías que coincidan');

        Category::factory()->create(['name' => 'Categoría existente']);

        $this->get(route('admin.categories.index', ['search' => 'Sin coincidencia']))
            ->assertOk()
            ->assertSee('No hay categorías que coincidan')
            ->assertDontSee('No hay categorías registradas');
    }

    public function test_employee_and_guest_keep_existing_index_protection(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)
            ->get(route('admin.categories.index', ['search' => 'algo']))
            ->assertForbidden();

        $this->post(route('logout'));
        $this->get(route('admin.categories.index', ['search' => 'algo']))
            ->assertRedirect(route('login'));
    }

    public function test_filtering_adds_no_routes(): void
    {
        $categoryRoutes = collect(Route::getRoutes()->getRoutesByName())
            ->keys()
            ->filter(fn (string $name): bool => str_starts_with($name, 'admin.categories.'))
            ->sort()
            ->values()
            ->all();

        $expectedRoutes = [
            'admin.categories.create',
            'admin.categories.destroy',
            'admin.categories.edit',
            'admin.categories.index',
            'admin.categories.show',
            'admin.categories.status',
            'admin.categories.store',
            'admin.categories.tree',
            'admin.categories.update',
        ];

        $this->assertSame($expectedRoutes, $categoryRoutes);
    }
}
