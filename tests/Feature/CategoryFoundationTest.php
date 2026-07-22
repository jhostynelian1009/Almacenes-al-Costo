<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class CategoryFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_table_contains_the_documented_columns(): void
    {
        $this->assertTrue(Schema::hasTable('categories'));
        $this->assertTrue(Schema::hasColumns('categories', [
            'id',
            'name',
            'slug',
            'description',
            'image',
            'icon',
            'parent_id',
            'display_order',
            'is_active',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_a_main_category_can_be_created(): void
    {
        $category = Category::factory()->main()->create();

        $this->assertModelExists($category);
        $this->assertNull($category->parent_id);
    }

    public function test_a_child_category_can_be_created(): void
    {
        $parent = Category::factory()->main()->create();
        $child = Category::factory()->childOf($parent)->create();

        $this->assertSame($parent->getKey(), $child->parent_id);
    }

    public function test_parent_returns_the_parent_category(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->childOf($parent)->create();

        $this->assertTrue($child->parent->is($parent));
    }

    public function test_children_returns_the_child_categories(): void
    {
        $parent = Category::factory()->create();
        $children = Category::factory()->count(2)->childOf($parent)->create();

        $this->assertCount(2, $parent->children);
        $this->assertTrue($parent->children->contains($children->first()));
        $this->assertTrue($parent->children->contains($children->last()));
    }

    public function test_the_hierarchy_supports_more_than_one_level(): void
    {
        $main = Category::factory()->main()->create();
        $child = Category::factory()->childOf($main)->create();
        $grandchild = Category::factory()->childOf($child)->create();

        $this->assertTrue($grandchild->parent->is($child));
        $this->assertTrue($grandchild->parent->parent->is($main));
    }

    public function test_name_must_be_unique(): void
    {
        $category = Category::factory()->create();

        $this->expectException(QueryException::class);

        Category::factory()->create(['name' => $category->name]);
    }

    public function test_slug_must_be_unique(): void
    {
        $category = Category::factory()->create();

        $this->expectException(QueryException::class);

        Category::factory()->create(['slug' => $category->slug]);
    }

    public function test_parent_id_must_reference_an_existing_category(): void
    {
        $this->expectException(QueryException::class);

        Category::factory()->create(['parent_id' => 999999]);
    }

    public function test_a_category_cannot_be_its_own_parent(): void
    {
        $category = Category::factory()->create();

        $this->expectException(LogicException::class);

        $category->update(['parent_id' => $category->getKey()]);
    }

    public function test_display_order_cannot_be_negative(): void
    {
        $this->expectException(LogicException::class);

        Category::factory()->create(['display_order' => -1]);
    }

    public function test_database_defaults_and_model_casts_are_applied(): void
    {
        $category = Category::query()->create([
            'name' => 'Neutral category',
            'slug' => 'neutral-category',
        ])->refresh();

        $this->assertSame(0, $category->display_order);
        $this->assertIsInt($category->display_order);
        $this->assertTrue($category->is_active);
        $this->assertIsBool($category->is_active);
    }

    public function test_active_scope_returns_only_active_categories(): void
    {
        $active = Category::factory()->active()->create();
        Category::factory()->inactive()->create();

        $categories = Category::query()->active()->get();

        $this->assertCount(1, $categories);
        $this->assertTrue($categories->first()->is($active));
    }

    public function test_ordered_scope_sorts_by_display_order_and_then_name(): void
    {
        Category::factory()->create(['name' => 'Gamma', 'display_order' => 2]);
        Category::factory()->create(['name' => 'Beta', 'display_order' => 1]);
        Category::factory()->create(['name' => 'Alpha', 'display_order' => 1]);

        $this->assertSame(
            ['Alpha', 'Beta', 'Gamma'],
            Category::query()->ordered()->pluck('name')->all(),
        );
    }

    public function test_a_category_with_children_cannot_be_deleted(): void
    {
        $parent = Category::factory()->create();
        Category::factory()->childOf($parent)->create();

        $this->expectException(QueryException::class);

        $parent->delete();
    }

    public function test_factory_generates_valid_unique_categories(): void
    {
        [$first, $second] = Category::factory()->count(2)->create();

        $this->assertNotSame($first->name, $second->name);
        $this->assertNotSame($first->slug, $second->slug);
        $this->assertGreaterThanOrEqual(0, $first->display_order);
        $this->assertModelExists($first);
        $this->assertModelExists($second);
    }

    public function test_factory_states_create_main_child_active_and_inactive_categories(): void
    {
        $parent = Category::factory()->main()->active()->create();
        $child = Category::factory()->childOf($parent)->inactive()->create();

        $this->assertNull($parent->parent_id);
        $this->assertTrue($parent->is_active);
        $this->assertSame($parent->getKey(), $child->parent_id);
        $this->assertFalse($child->is_active);
    }
}
