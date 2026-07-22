<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_category_administration(): void
    {
        $this->get(route('admin.categories.index'))->assertRedirect(route('login'));
    }

    public function test_active_admin_can_access_every_category_screen(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)->get(route('admin.categories.index'))->assertOk()->assertViewIs('admin.categories.index');
        $this->get(route('admin.categories.create'))->assertOk()->assertViewIs('admin.categories.create');
        $this->get(route('admin.categories.show', $category))->assertOk()->assertViewIs('admin.categories.show');
        $this->get(route('admin.categories.edit', $category))->assertOk()->assertViewIs('admin.categories.edit');
    }

    public function test_employee_cannot_access_category_administration(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)
            ->get(route('admin.categories.index'))
            ->assertForbidden();
    }

    public function test_inactive_admin_cannot_access_category_administration(): void
    {
        $admin = User::factory()->admin()->inactive()->create();

        $this->actingAs($admin)
            ->get(route('admin.categories.index'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_index_uses_admin_layout_and_renders_empty_state(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('Categorías')
            ->assertSee('No hay categorías registradas')
            ->assertSee(route('admin.categories.create'));
    }

    public function test_index_displays_documented_category_columns(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->create(['name' => 'Principal', 'display_order' => 4]);
        Category::factory()->childOf($parent)->inactive()->create([
            'name' => 'Secundaria',
            'slug' => 'secundaria',
            'display_order' => 7,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSeeInOrder(['Nombre', 'Slug', 'Categoría padre', 'Estado', 'Orden', 'Acciones'])
            ->assertSee('Secundaria')
            ->assertSee('secundaria')
            ->assertSee('Principal')
            ->assertSee('Inactiva');
    }

    public function test_admin_can_create_a_main_category_with_server_generated_slug(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => '  Tecnología   para el Hogar  ',
            'description' => '  Descripción de categoría  ',
            'parent_id' => '',
        ]);

        $category = Category::query()->sole();

        $response->assertRedirect(route('admin.categories.show', $category))
            ->assertSessionHas('success');
        $this->assertSame('Tecnología para el Hogar', $category->name);
        $this->assertSame('tecnologia-para-el-hogar', $category->slug);
        $this->assertSame('Descripción de categoría', $category->description);
        $this->assertNull($category->parent_id);
        $this->assertTrue($category->is_active);
        $this->assertSame(0, $category->display_order);
    }

    public function test_admin_can_create_a_subcategory(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->create();

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Subcategoría',
            'parent_id' => $parent->getKey(),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'name' => 'Subcategoría',
            'slug' => 'subcategoria',
            'parent_id' => $parent->getKey(),
        ]);
    }

    public function test_store_validates_required_unique_name_and_generated_slug(): void
    {
        $admin = User::factory()->admin()->create();
        Category::factory()->create(['name' => 'Tecnología', 'slug' => 'tecnologia']);

        $this->actingAs($admin)
            ->from(route('admin.categories.create'))
            ->post(route('admin.categories.store'), ['name' => ''])
            ->assertRedirect(route('admin.categories.create'))
            ->assertSessionHasErrors(['name', 'slug']);

        $this->post(route('admin.categories.store'), ['name' => 'Tecnología'])
            ->assertSessionHasErrors(['name', 'slug']);

        $this->post(route('admin.categories.store'), ['name' => 'Tecnologia'])
            ->assertSessionHasErrors('slug');
    }

    public function test_store_rejects_a_nonexistent_parent(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Categoría válida',
                'parent_id' => 999999,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_show_displays_documented_details_without_optional_media_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->create(['name' => 'Principal']);
        $category = Category::factory()->childOf($parent)->create([
            'name' => 'Detalle',
            'slug' => 'detalle',
            'description' => 'Descripción visible',
            'image' => 'categories/image.jpg',
            'icon' => 'icon-name',
            'display_order' => 3,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.categories.show', $category))
            ->assertOk()
            ->assertSee('Detalle')
            ->assertSee('detalle')
            ->assertSee('Descripción visible')
            ->assertSee('Principal')
            ->assertSee('Orden de visualización')
            ->assertDontSee('categories/image.jpg')
            ->assertDontSee('icon-name');
    }

    public function test_update_regenerates_slug_when_name_changes_and_preserves_non_editable_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->create();
        $category = Category::factory()->create([
            'name' => 'Nombre anterior',
            'slug' => 'nombre-anterior',
            'image' => 'categories/original.jpg',
            'icon' => 'original-icon',
            'display_order' => 12,
            'is_active' => false,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.categories.update', $category), [
            'name' => 'Nombre nuevo',
            'description' => 'Descripción nueva',
            'parent_id' => $parent->getKey(),
            'image' => 'categories/changed.jpg',
            'icon' => 'changed-icon',
            'display_order' => 99,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.categories.show', $category))
            ->assertSessionHas('success');
        $category->refresh();
        $this->assertSame('Nombre nuevo', $category->name);
        $this->assertSame('nombre-nuevo', $category->slug);
        $this->assertSame($parent->getKey(), $category->parent_id);
        $this->assertSame('categories/original.jpg', $category->image);
        $this->assertSame('original-icon', $category->icon);
        $this->assertSame(12, $category->display_order);
        $this->assertFalse($category->is_active);
    }

    public function test_update_preserves_slug_when_name_does_not_change(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create([
            'name' => 'Nombre estable',
            'slug' => 'slug-personalizado-existente',
        ]);

        $this->actingAs($admin)->put(route('admin.categories.update', $category), [
            'name' => 'Nombre estable',
            'description' => 'Actualizada',
            'parent_id' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame('slug-personalizado-existente', $category->fresh()->slug);
    }

    public function test_update_rejects_own_category_as_parent(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)->put(route('admin.categories.update', $category), [
            'name' => $category->name,
            'parent_id' => $category->getKey(),
        ])->assertSessionHasErrors('parent_id');
    }

    public function test_admin_can_delete_a_category_without_relations(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.categories.destroy', $category))
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('success');

        $this->assertModelMissing($category);
    }

    public function test_category_with_children_cannot_be_deleted_and_returns_friendly_error(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->create();
        Category::factory()->childOf($parent)->create();

        $this->actingAs($admin)
            ->delete(route('admin.categories.destroy', $parent))
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('error', 'No se puede eliminar la categoría porque tiene subcategorías asociadas.');

        $this->assertModelExists($parent);
    }

    public function test_category_resource_has_only_the_documented_rest_routes_and_middleware(): void
    {
        $expectedRoutes = [
            'admin.categories.index',
            'admin.categories.create',
            'admin.categories.store',
            'admin.categories.show',
            'admin.categories.edit',
            'admin.categories.update',
            'admin.categories.destroy',
        ];

        $actualRoutes = collect(Route::getRoutes()->getRoutesByName())
            ->keys()
            ->filter(fn (string $name): bool => str_starts_with($name, 'admin.categories.'))
            ->sort()
            ->values()
            ->all();

        sort($expectedRoutes);
        $this->assertSame($expectedRoutes, $actualRoutes);

        foreach ($expectedRoutes as $routeName) {
            $middleware = Route::getRoutes()->getByName($routeName)->gatherMiddleware();
            $this->assertContains('auth', $middleware);
            $this->assertContains('active', $middleware);
            $this->assertContains('admin', $middleware);
        }

        $this->assertTrue(Route::has('categories.index'));
        $this->assertFalse(Route::has('categories.create'));
    }

    public function test_existing_public_home_and_admin_dashboard_remain_accessible_to_their_authorized_users(): void
    {
        $employee = User::factory()->create();

        $this->get(route('home'))->assertOk();
        $this->actingAs($employee)->get(route('admin.dashboard'))->assertOk();
    }
}
