<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CategoryEpicIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_completes_the_category_lifecycle_without_losing_deferred_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Raiz Integral',
                'description' => 'Categoria principal del flujo.',
                'parent_id' => '',
                'display_order' => 9,
            ])
            ->assertSessionHasNoErrors();

        $root = Category::query()->where('name', 'Raiz Integral')->sole();

        $this->assertSame('raiz-integral', $root->slug);
        $this->assertNull($root->parent_id);
        $this->assertTrue($root->is_active);

        $this->post(route('admin.categories.store'), [
            'name' => 'Hija Integral',
            'parent_id' => $root->getKey(),
            'display_order' => 5,
        ])->assertSessionHasNoErrors();

        $child = Category::query()->where('name', 'Hija Integral')->sole();
        $child->update([
            'image' => 'categories/preserved.webp',
            'icon' => 'preserved-icon',
        ]);

        $this->put(route('admin.categories.update', $child), [
            'name' => 'Hija Integral Editada',
            'description' => 'Descripcion editada.',
            'parent_id' => $root->getKey(),
            'display_order' => 1,
            'image' => 'categories/ignored.webp',
            'icon' => 'ignored-icon',
            'is_active' => false,
        ])->assertSessionHasNoErrors();

        $child->refresh();

        $this->assertSame('hija-integral-editada', $child->slug);
        $this->assertSame(1, $child->display_order);
        $this->assertSame('categories/preserved.webp', $child->image);
        $this->assertSame('preserved-icon', $child->icon);
        $this->assertTrue($child->is_active);

        $this->patch(route('admin.categories.status', $child), ['is_active' => false])
            ->assertSessionHasNoErrors();
        $this->assertFalse($child->fresh()->is_active);

        $this->patch(route('admin.categories.status', $child), ['is_active' => true])
            ->assertSessionHasNoErrors();
        $this->assertTrue($child->fresh()->is_active);

        $blockedDeletion = $this->delete(route('admin.categories.destroy', $root))
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas(
                'error',
                'No se puede eliminar la categoría porque tiene subcategorías asociadas.',
            );

        $this->assertStringNotContainsString(
            'sql',
            strtolower((string) $blockedDeletion->getSession()->get('error')),
        );
        $this->assertModelExists($root);

        $this->delete(route('admin.categories.destroy', $child))
            ->assertSessionHas('success');

        $this->assertModelMissing($child);
        $this->assertModelExists($root);

        $this->delete(route('admin.categories.destroy', $root))
            ->assertSessionHas('success');
        $this->assertModelMissing($root);
    }

    public function test_three_level_hierarchy_integrates_cycles_visibility_order_and_query_counts(): void
    {
        $admin = User::factory()->admin()->create();
        $laterRoot = Category::factory()->create([
            'name' => 'Raiz Zulu',
            'display_order' => 2,
        ]);
        $firstRoot = Category::factory()->create([
            'name' => 'Raiz Alfa',
            'display_order' => 1,
        ]);
        $inactiveChild = Category::factory()->childOf($firstRoot)->inactive()->create([
            'name' => 'Hija Inactiva',
            'display_order' => 0,
        ]);
        $hiddenDescendant = Category::factory()->childOf($inactiveChild)->active()->create([
            'name' => 'Nieta Oculta',
            'display_order' => 0,
        ]);
        $alphaChild = Category::factory()->childOf($firstRoot)->create([
            'name' => 'Hija Alfa',
            'display_order' => 1,
        ]);
        $grandchild = Category::factory()->childOf($alphaChild)->create([
            'name' => 'Nieta Visible',
            'display_order' => 0,
        ]);
        $betaChild = Category::factory()->childOf($firstRoot)->create([
            'name' => 'Hija Beta',
            'display_order' => 1,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.categories.edit', $firstRoot))
            ->put(route('admin.categories.update', $firstRoot), [
                'name' => $firstRoot->name,
                'parent_id' => $grandchild->getKey(),
                'display_order' => $firstRoot->display_order,
            ])
            ->assertSessionHasErrors('parent_id');

        $this->assertNull($firstRoot->fresh()->parent_id);

        $categoryQueries = 0;

        DB::listen(function (QueryExecuted $query) use (&$categoryQueries): void {
            if (preg_match('/\bfrom\s+[`"]?categories[`"]?/i', $query->sql) === 1) {
                $categoryQueries++;
            }
        });

        $this->get(route('admin.categories.tree'))
            ->assertOk()
            ->assertSeeInOrder([
                $firstRoot->name,
                $inactiveChild->name,
                $hiddenDescendant->name,
                $alphaChild->name,
                $grandchild->name,
                $betaChild->name,
                $laterRoot->name,
            ])
            ->assertSee('Inactiva');

        $this->assertSame(1, $categoryQueries);

        $categoryQueries = 0;

        $this->get(route('categories.index'))
            ->assertOk()
            ->assertSeeInOrder([
                $firstRoot->name,
                $alphaChild->name,
                $grandchild->name,
                $betaChild->name,
                $laterRoot->name,
            ])
            ->assertDontSee($inactiveChild->name)
            ->assertDontSee($hiddenDescendant->name);

        $this->assertSame(1, $categoryQueries);
    }

    public function test_combined_search_filters_and_pagination_keep_the_complete_query_contract(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->create(['name' => 'Padre Integrado']);
        $otherParent = Category::factory()->create(['name' => 'Padre Excluido']);

        foreach (range(0, 15) as $index) {
            Category::factory()->childOf($parent)->active()->create([
                'name' => sprintf('Integrada %02d', $index),
                'display_order' => $index,
            ]);
        }

        Category::factory()->childOf($parent)->inactive()->create(['name' => 'Integrada Inactiva']);
        Category::factory()->childOf($otherParent)->active()->create(['name' => 'Integrada Otro Padre']);
        Category::factory()->main()->active()->create(['name' => 'Integrada Principal']);

        $filters = [
            'search' => 'Integrada',
            'status' => 'active',
            'parent_id' => $parent->getKey(),
            'level' => 'child',
            'page' => 2,
        ];

        $this->actingAs($admin)
            ->get(route('admin.categories.index', $filters))
            ->assertOk()
            ->assertViewHas('categories', function (LengthAwarePaginator $categories) use ($filters): bool {
                parse_str((string) parse_url($categories->url(2), PHP_URL_QUERY), $query);

                return $categories->perPage() === 15
                    && $categories->total() === 16
                    && $categories->currentPage() === 2
                    && $categories->count() === 1
                    && $categories->first()->name === 'Integrada 15'
                    && $query['search'] === $filters['search']
                    && $query['status'] === $filters['status']
                    && $query['parent_id'] === (string) $filters['parent_id']
                    && $query['level'] === $filters['level']
                    && $query['page'] === '2';
            });
    }

    public function test_authorization_and_public_route_contract_hold_for_every_category_action(): void
    {
        $employee = User::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($employee);

        foreach ($this->administrativeRequests($category) as $request) {
            $request()->assertForbidden();
        }

        $this->post(route('logout'));
        $this->assertGuest();

        foreach ($this->administrativeRequests($category) as $request) {
            $request()->assertRedirect(route('login'));
        }

        $this->assertModelExists($category);

        foreach (['home', 'catalog.index', 'categories.index', 'promotions.index', 'information'] as $routeName) {
            $this->get(route($routeName))->assertOk();
        }

        $publicCategoryRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'categorias'))
            ->values();

        $this->assertCount(1, $publicCategoryRoutes);
        $this->assertSame('categories.index', $publicCategoryRoutes->first()->getName());
        $this->assertSame(['GET', 'HEAD'], $publicCategoryRoutes->first()->methods());

        foreach (['categories.create', 'categories.store', 'categories.update', 'categories.destroy'] as $routeName) {
            $this->assertFalse(Route::has($routeName));
        }

        $this->assertFalse(Route::has('register'));
        $this->get('/register')->assertNotFound();
    }

    /**
     * @return array<int, callable(): TestResponse>
     */
    private function administrativeRequests(Category $category): array
    {
        return [
            fn () => $this->get(route('admin.categories.index')),
            fn () => $this->get(route('admin.categories.create')),
            fn () => $this->post(route('admin.categories.store'), ['name' => 'Acceso no autorizado']),
            fn () => $this->get(route('admin.categories.show', $category)),
            fn () => $this->get(route('admin.categories.edit', $category)),
            fn () => $this->put(route('admin.categories.update', $category), [
                'name' => $category->name,
                'parent_id' => '',
                'display_order' => $category->display_order,
            ]),
            fn () => $this->patch(route('admin.categories.status', $category), ['is_active' => false]),
            fn () => $this->delete(route('admin.categories.destroy', $category)),
            fn () => $this->get(route('admin.categories.tree')),
        ];
    }
}
