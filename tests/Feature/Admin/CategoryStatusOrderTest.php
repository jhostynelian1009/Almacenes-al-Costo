<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CategoryStatusOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_deactivate_a_category(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->active()->create();

        $this->actingAs($admin)
            ->patch(route('admin.categories.status', $category), ['is_active' => false])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('success', 'Categoría desactivada correctamente.');

        $this->assertFalse($category->fresh()->is_active);
    }

    public function test_admin_can_activate_an_inactive_category(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->inactive()->create();

        $this->actingAs($admin)
            ->patch(route('admin.categories.status', $category), ['is_active' => true])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('success', 'Categoría activada correctamente.');

        $this->assertTrue($category->fresh()->is_active);
    }

    public function test_employee_cannot_change_category_status(): void
    {
        $employee = User::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($employee)
            ->patch(route('admin.categories.status', $category), ['is_active' => false])
            ->assertForbidden();

        $this->assertTrue($category->fresh()->is_active);
    }

    public function test_guest_is_redirected_to_login_when_changing_category_status(): void
    {
        $category = Category::factory()->create();

        $this->patch(route('admin.categories.status', $category), ['is_active' => false])
            ->assertRedirect(route('login'));

        $this->assertTrue($category->fresh()->is_active);
    }

    public function test_status_action_requires_patch(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.categories.status', $category), ['is_active' => false])
            ->assertStatus(405);
    }

    public function test_status_action_requires_a_boolean_is_active_value(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.categories.status', $category))
            ->assertSessionHasErrors('is_active');

        $this->patch(route('admin.categories.status', $category), ['is_active' => 'invalid'])
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($category->fresh()->is_active);
    }

    public function test_status_action_changes_no_other_category_field(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->create();
        $otherParent = Category::factory()->create();
        $category = Category::factory()->childOf($parent)->create([
            'name' => 'Original',
            'slug' => 'original',
            'description' => 'Descripción original',
            'image' => 'categories/original.jpg',
            'icon' => 'original-icon',
            'display_order' => 8,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->patch(route('admin.categories.status', $category), [
            'is_active' => false,
            'name' => 'Modificada',
            'slug' => 'modificada',
            'description' => 'Descripción modificada',
            'parent_id' => $otherParent->getKey(),
            'display_order' => 99,
            'image' => 'categories/modified.jpg',
            'icon' => 'modified-icon',
        ])->assertSessionHasNoErrors();

        $category->refresh();
        $this->assertFalse($category->is_active);
        $this->assertSame('Original', $category->name);
        $this->assertSame('original', $category->slug);
        $this->assertSame('Descripción original', $category->description);
        $this->assertSame($parent->getKey(), $category->parent_id);
        $this->assertSame(8, $category->display_order);
        $this->assertSame('categories/original.jpg', $category->image);
        $this->assertSame('original-icon', $category->icon);
    }

    public function test_deactivating_a_category_does_not_deactivate_its_children(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->active()->create();
        $child = Category::factory()->childOf($parent)->active()->create();

        $this->actingAs($admin)
            ->patch(route('admin.categories.status', $parent), ['is_active' => false])
            ->assertSessionHasNoErrors();

        $this->assertFalse($parent->fresh()->is_active);
        $this->assertTrue($child->fresh()->is_active);
    }

    public function test_deactivating_a_child_does_not_change_its_parent(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Category::factory()->active()->create();
        $child = Category::factory()->childOf($parent)->active()->create();

        $this->actingAs($admin)
            ->patch(route('admin.categories.status', $child), ['is_active' => false])
            ->assertSessionHasNoErrors();

        $this->assertTrue($parent->fresh()->is_active);
        $this->assertFalse($child->fresh()->is_active);
    }

    public function test_admin_can_define_display_order_when_creating_a_category(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Categoría ordenada',
            'display_order' => 14,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', [
            'name' => 'Categoría ordenada',
            'display_order' => 14,
        ]);
    }

    public function test_admin_can_update_display_order(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create(['display_order' => 2]);

        $this->actingAs($admin)->put(route('admin.categories.update', $category), [
            'name' => $category->name,
            'description' => $category->description,
            'parent_id' => $category->parent_id,
            'display_order' => 20,
        ])->assertSessionHasNoErrors();

        $this->assertSame(20, $category->fresh()->display_order);
    }

    public function test_negative_and_decimal_display_order_values_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Orden negativo',
            'display_order' => -1,
        ])->assertSessionHasErrors('display_order');

        $this->post(route('admin.categories.store'), [
            'name' => 'Orden decimal',
            'display_order' => 1.5,
        ])->assertSessionHasErrors('display_order');

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_display_order_defaults_to_zero_when_omitted_on_create(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Orden predeterminado',
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, Category::query()->sole()->display_order);
    }

    public function test_repeated_display_order_values_are_allowed(): void
    {
        $admin = User::factory()->admin()->create();
        Category::factory()->create(['display_order' => 5]);

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Mismo orden',
            'display_order' => 5,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Category::query()->where('display_order', 5)->count());
    }

    public function test_index_orders_categories_by_display_order_and_then_name(): void
    {
        $admin = User::factory()->admin()->create();
        Category::factory()->create(['name' => 'Zulu única', 'display_order' => 2]);
        Category::factory()->create(['name' => 'Beta única', 'display_order' => 1]);
        Category::factory()->create(['name' => 'Alfa única', 'display_order' => 1]);

        $this->actingAs($admin)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSeeInOrder(['Alfa única', 'Beta única', 'Zulu única']);
    }

    public function test_index_shows_status_order_and_explicit_status_actions(): void
    {
        $admin = User::factory()->admin()->create();
        Category::factory()->active()->create(['name' => 'Categoría activa', 'display_order' => 3]);
        Category::factory()->inactive()->create(['name' => 'Categoría inactiva', 'display_order' => 6]);

        $this->actingAs($admin)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('Activa')
            ->assertSee('Inactiva')
            ->assertSee('Desactivar')
            ->assertSee('Activar')
            ->assertSee('3')
            ->assertSee('6');
    }

    public function test_create_and_edit_forms_show_display_order_field(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create(['display_order' => 11]);

        $this->actingAs($admin)
            ->get(route('admin.categories.create'))
            ->assertOk()
            ->assertSee('Orden de visualización')
            ->assertSee('name="display_order"', false)
            ->assertSee('value="0"', false);

        $this->get(route('admin.categories.edit', $category))
            ->assertOk()
            ->assertSee('name="display_order"', false)
            ->assertSee('value="11"', false);
    }

    public function test_status_route_uses_administrative_middleware_and_no_public_route_is_added(): void
    {
        $route = Route::getRoutes()->getByName('admin.categories.status');

        $this->assertNotNull($route);
        $this->assertSame(['PATCH'], $route->methods());
        $this->assertSame('admin/categories/{category}/status', $route->uri());
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('active', $route->gatherMiddleware());
        $this->assertContains('admin', $route->gatherMiddleware());
        $this->assertFalse(Route::has('categories.status'));
    }
}
