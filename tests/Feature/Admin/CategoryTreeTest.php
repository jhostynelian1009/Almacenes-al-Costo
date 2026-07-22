<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use LogicException;
use Tests\TestCase;

class CategoryTreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_access_tree_and_existing_show_route_still_works(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.categories.tree'))
            ->assertOk()
            ->assertViewIs('admin.categories.tree');

        $this->get(route('admin.categories.show', $category))
            ->assertOk()
            ->assertViewIs('admin.categories.show');
    }

    public function test_employee_and_guest_cannot_access_tree(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)
            ->get(route('admin.categories.tree'))
            ->assertForbidden();

        $this->post(route('logout'));
        $this->get(route('admin.categories.tree'))
            ->assertRedirect(route('login'));
    }

    public function test_tree_route_precedes_resource_binding_and_uses_admin_middleware(): void
    {
        $treeRoute = Route::getRoutes()->getByName('admin.categories.tree');

        $this->assertNotNull($treeRoute);
        $this->assertSame('admin/categories/tree', $treeRoute->uri());
        $this->assertSame(['GET', 'HEAD'], $treeRoute->methods());
        $this->assertContains('auth', $treeRoute->gatherMiddleware());
        $this->assertContains('active', $treeRoute->gatherMiddleware());
        $this->assertContains('admin', $treeRoute->gatherMiddleware());
    }

    public function test_tree_renders_main_child_and_three_level_categories(): void
    {
        $admin = User::factory()->admin()->create();
        $main = Category::factory()->create(['name' => 'Nivel principal']);
        $child = Category::factory()->childOf($main)->create(['name' => 'Nivel secundario']);
        $grandchild = Category::factory()->childOf($child)->create(['name' => 'Nivel tercero']);

        $this->actingAs($admin)
            ->get(route('admin.categories.tree'))
            ->assertOk()
            ->assertSeeInOrder([$main->name, $child->name, $grandchild->name])
            ->assertSee('Nivel 1')
            ->assertSee('Nivel 2')
            ->assertSee('Nivel 3')
            ->assertSee(route('admin.categories.show', $grandchild))
            ->assertSee(route('admin.categories.edit', $grandchild));
    }

    public function test_each_tree_level_is_ordered_by_display_order_and_name(): void
    {
        $admin = User::factory()->admin()->create();
        $alphaRoot = Category::factory()->create(['name' => 'Alfa raíz', 'display_order' => 1]);
        Category::factory()->create(['name' => 'Zulu raíz', 'display_order' => 2]);
        Category::factory()->childOf($alphaRoot)->create(['name' => 'Beta hija', 'display_order' => 1]);
        Category::factory()->childOf($alphaRoot)->create(['name' => 'Alfa hija', 'display_order' => 1]);

        $this->actingAs($admin)
            ->get(route('admin.categories.tree'))
            ->assertOk()
            ->assertSeeInOrder(['Alfa raíz', 'Alfa hija', 'Beta hija', 'Zulu raíz']);
    }

    public function test_inactive_categories_remain_visible_in_admin_tree(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->inactive()->create(['name' => 'Categoría inactiva visible']);

        $this->actingAs($admin)
            ->get(route('admin.categories.tree'))
            ->assertOk()
            ->assertSee($category->name)
            ->assertSee('Inactiva');
    }

    public function test_tree_uses_one_category_query_instead_of_querying_each_node(): void
    {
        $admin = User::factory()->admin()->create();
        $main = Category::factory()->create();
        $child = Category::factory()->childOf($main)->create();
        Category::factory()->childOf($child)->create();
        Category::factory()->count(3)->childOf($main)->create();
        $categoryQueries = 0;

        DB::listen(function (QueryExecuted $query) use (&$categoryQueries): void {
            $sql = strtolower($query->sql);

            if (str_contains($sql, 'from "categories"') || str_contains($sql, 'from `categories`')) {
                $categoryQueries++;
            }
        });

        $this->actingAs($admin)
            ->get(route('admin.categories.tree'))
            ->assertOk();

        $this->assertSame(1, $categoryQueries);
    }

    public function test_tree_has_an_accessible_empty_state(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.categories.tree'))
            ->assertOk()
            ->assertSee('No hay categorías para mostrar')
            ->assertSee(route('admin.categories.create'));
    }

    public function test_descendant_ids_include_direct_and_deep_descendants_only(): void
    {
        $main = Category::factory()->create();
        $child = Category::factory()->childOf($main)->create();
        $grandchild = Category::factory()->childOf($child)->create();
        $unrelated = Category::factory()->create();

        $this->assertEqualsCanonicalizing(
            [$child->getKey(), $grandchild->getKey()],
            $main->descendantIds(),
        );
        $this->assertNotContains($unrelated->getKey(), $main->descendantIds());
    }

    public function test_update_request_rejects_self_direct_child_and_deep_descendant_as_parent(): void
    {
        $admin = User::factory()->admin()->create();
        $main = Category::factory()->create();
        $child = Category::factory()->childOf($main)->create();
        $grandchild = Category::factory()->childOf($child)->create();

        foreach ([$main, $child, $grandchild] as $invalidParent) {
            $this->actingAs($admin)->put(route('admin.categories.update', $main), [
                'name' => $main->name,
                'parent_id' => $invalidParent->getKey(),
                'display_order' => $main->display_order,
            ])->assertSessionHasErrors('parent_id');
        }

        $this->assertNull($main->fresh()->parent_id);
    }

    public function test_cycle_validation_returns_a_comprehensible_message(): void
    {
        $admin = User::factory()->admin()->create();
        $main = Category::factory()->create();
        $child = Category::factory()->childOf($main)->create();

        $this->actingAs($admin)->put(route('admin.categories.update', $main), [
            'name' => $main->name,
            'parent_id' => $child->getKey(),
            'display_order' => $main->display_order,
        ])->assertSessionHasErrors([
            'parent_id' => 'La categoría padre seleccionada generaría un ciclo jerárquico.',
        ]);
    }

    public function test_valid_unrelated_parent_and_null_parent_are_allowed(): void
    {
        $admin = User::factory()->admin()->create();
        $currentParent = Category::factory()->create();
        $validParent = Category::factory()->create();
        $category = Category::factory()->childOf($currentParent)->create();

        $this->actingAs($admin)->put(route('admin.categories.update', $category), [
            'name' => $category->name,
            'parent_id' => $validParent->getKey(),
            'display_order' => $category->display_order,
        ])->assertSessionHasNoErrors();

        $this->assertSame($validParent->getKey(), $category->fresh()->parent_id);

        $this->put(route('admin.categories.update', $category), [
            'name' => $category->name,
            'parent_id' => '',
            'display_order' => $category->display_order,
        ])->assertSessionHasNoErrors();

        $this->assertNull($category->fresh()->parent_id);
    }

    public function test_edit_parent_selector_excludes_self_and_all_descendants_but_keeps_valid_categories(): void
    {
        $admin = User::factory()->admin()->create();
        $main = Category::factory()->create();
        $child = Category::factory()->childOf($main)->create();
        $grandchild = Category::factory()->childOf($child)->create();
        $valid = Category::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.categories.edit', $main))
            ->assertOk()
            ->assertViewHas('parentCategories', function ($categories) use ($main, $child, $grandchild, $valid): bool {
                $ids = $categories->modelKeys();

                return ! in_array($main->getKey(), $ids, true)
                    && ! in_array($child->getKey(), $ids, true)
                    && ! in_array($grandchild->getKey(), $ids, true)
                    && in_array($valid->getKey(), $ids, true);
            });
    }

    public function test_edit_selector_keeps_the_current_valid_parent_selected(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->create();
        $category = Category::factory()->childOf($parent)->create();

        $this->actingAs($admin)
            ->get(route('admin.categories.edit', $category))
            ->assertOk()
            ->assertViewHas('parentCategories', fn ($categories): bool => $categories->contains($parent))
            ->assertSee('value="'.$parent->getKey().'" selected', false);
    }

    public function test_model_rejects_an_indirect_cycle_during_direct_update(): void
    {
        $main = Category::factory()->create();
        $child = Category::factory()->childOf($main)->create();
        $grandchild = Category::factory()->childOf($child)->create();

        try {
            $main->update(['parent_id' => $grandchild->getKey()]);
            $this->fail('The model should reject an indirect category cycle.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('descendants', $exception->getMessage());
        }

        $this->assertNull($main->fresh()->parent_id);
    }

    public function test_tree_adds_no_public_routes(): void
    {
        $this->assertTrue(Route::has('admin.categories.tree'));
        $this->assertFalse(Route::has('categories.tree'));
        $this->get('/categorias/tree')->assertNotFound();
    }
}
