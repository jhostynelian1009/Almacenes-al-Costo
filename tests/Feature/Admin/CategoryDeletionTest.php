<?php

namespace Tests\Feature\Admin;

use App\Exceptions\CategoryDeletionException;
use App\Models\Category;
use App\Models\User;
use App\Services\CategoryDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CategoryDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_deletes_a_category_without_blockers(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.categories.destroy', $category))
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('success', 'Categoría eliminada correctamente.');

        $this->assertModelMissing($category);
    }

    public function test_active_child_blocks_parent_deletion(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->create();
        $child = Category::factory()->childOf($parent)->active()->create();

        $this->actingAs($admin)
            ->delete(route('admin.categories.destroy', $parent))
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('error', 'No se puede eliminar la categoría porque tiene subcategorías asociadas.');

        $this->assertModelExists($parent);
        $this->assertModelExists($child);
    }

    public function test_inactive_child_also_blocks_parent_deletion(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->create();
        $child = Category::factory()->childOf($parent)->inactive()->create();

        $this->actingAs($admin)
            ->delete(route('admin.categories.destroy', $parent))
            ->assertSessionHas('error');

        $this->assertModelExists($parent);
        $this->assertModelExists($child);
    }

    public function test_deep_descendants_block_deletion_through_the_direct_child(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->create();
        $child = Category::factory()->childOf($parent)->create();
        $grandchild = Category::factory()->childOf($child)->create();

        $this->actingAs($admin)
            ->delete(route('admin.categories.destroy', $parent))
            ->assertSessionHas('error');

        $this->assertModelExists($parent);
        $this->assertModelExists($child);
        $this->assertModelExists($grandchild);
    }

    public function test_leaf_subcategory_can_be_deleted_without_modifying_its_parent(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->create();
        $child = Category::factory()->childOf($parent)->create();
        $parentAttributes = $parent->getAttributes();

        $this->actingAs($admin)
            ->delete(route('admin.categories.destroy', $child))
            ->assertSessionHas('success');

        $this->assertModelMissing($child);
        $this->assertEquals($parentAttributes, $parent->fresh()->getAttributes());
    }

    public function test_rejected_deletion_changes_neither_category_nor_children(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->create();
        $child = Category::factory()->childOf($parent)->create();
        $parentAttributes = $parent->getAttributes();
        $childAttributes = $child->getAttributes();

        $this->actingAs($admin)
            ->delete(route('admin.categories.destroy', $parent))
            ->assertSessionHas('error');

        $this->assertEquals($parentAttributes, $parent->fresh()->getAttributes());
        $this->assertEquals($childAttributes, $child->fresh()->getAttributes());
    }

    public function test_service_uses_a_domain_exception_when_subcategories_block_deletion(): void
    {
        $parent = Category::factory()->create();
        Category::factory()->childOf($parent)->create();

        $this->expectException(CategoryDeletionException::class);
        $this->expectExceptionMessage('subcategorías asociadas');

        app(CategoryDeletionService::class)->delete($parent);
    }

    public function test_expected_integrity_failure_is_translated_and_transaction_is_rolled_back(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $injectConcurrentChild = true;

        Category::deleting(function (Category $deleting) use ($category, &$injectConcurrentChild): void {
            if ($injectConcurrentChild && $deleting->is($category)) {
                $injectConcurrentChild = false;
                Category::factory()->childOf($deleting)->create();
            }
        });

        try {
            $response = $this->actingAs($admin)
                ->delete(route('admin.categories.destroy', $category));
        } finally {
            $injectConcurrentChild = false;
        }

        $response
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('error', 'No se puede eliminar la categoría porque tiene relaciones asociadas.');

        $message = session('error');

        $this->assertIsString($message);
        $this->assertStringNotContainsString('SQLSTATE', $message);
        $this->assertStringNotContainsString('categories_parent_id_foreign', $message);
        $this->assertStringNotContainsString('delete from', strtolower($message));
        $this->assertModelExists($category);
        $this->assertDatabaseCount('categories', 1);
    }

    public function test_guest_and_employee_cannot_delete_categories(): void
    {
        $category = Category::factory()->create();

        $this->delete(route('admin.categories.destroy', $category))
            ->assertRedirect(route('login'));

        $employee = User::factory()->create();

        $this->actingAs($employee)
            ->delete(route('admin.categories.destroy', $category))
            ->assertForbidden();

        $this->assertModelExists($category);
    }

    public function test_nonexistent_category_returns_not_found(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete('/admin/categories/999999')
            ->assertNotFound();
    }

    public function test_destroy_route_keeps_delete_method_and_administrative_middleware(): void
    {
        $destroyRoute = Route::getRoutes()->getByName('admin.categories.destroy');

        $this->assertNotNull($destroyRoute);
        $this->assertSame(['DELETE'], $destroyRoute->methods());
        $this->assertContains('auth', $destroyRoute->gatherMiddleware());
        $this->assertContains('active', $destroyRoute->gatherMiddleware());
        $this->assertContains('admin', $destroyRoute->gatherMiddleware());
    }

    public function test_deletion_adds_no_routes(): void
    {
        $categoryRoutes = collect(Route::getRoutes()->getRoutesByName())
            ->keys()
            ->filter(fn (string $name): bool => str_starts_with($name, 'admin.categories.'))
            ->values();

        $this->assertCount(9, $categoryRoutes);
        $this->assertTrue($categoryRoutes->contains('admin.categories.destroy'));
    }

    public function test_index_explains_permanent_deletion_and_keeps_an_accessible_button(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create(['name' => 'Categoría eliminable']);

        $this->actingAs($admin)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('La eliminación es permanente.')
            ->assertSee('Eliminar');
    }
}
