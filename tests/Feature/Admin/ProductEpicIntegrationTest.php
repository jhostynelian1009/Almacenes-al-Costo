<?php

namespace Tests\Feature\Admin;

use App\Exceptions\CategoryDeletionException;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ProductEpicIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_completes_the_product_lifecycle_with_images_filters_and_soft_delete(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $originalCategory = Category::factory()->create(['name' => 'Categoría original']);
        $newCategory = Category::factory()->inactive()->create(['name' => 'Categoría destino']);

        $this->actingAs($admin)
            ->post(route('admin.products.store'), $this->validPayload($originalCategory, [
                'name' => 'Producto integral',
                'sku' => 'EP006-INTEGRAL',
                'price' => '125.40',
                'image' => $this->fakeImage('principal.jpg'),
                'deleted_at' => now(),
                'stock' => 500,
            ]))
            ->assertSessionHasNoErrors();

        $product = Product::query()->sole();
        $originalPath = $product->image;

        $this->assertSame($originalCategory->getKey(), $product->category_id);
        $this->assertSame('EP006-INTEGRAL', $product->sku);
        $this->assertSame('125.40', $product->price);
        $this->assertTrue($product->is_active);
        $this->assertNull($product->deleted_at);
        $this->assertIsString($originalPath);
        $this->assertStringStartsWith('products/', $originalPath);
        Storage::disk('public')->assertExists($originalPath);

        $this->get(route('admin.products.show', $product))
            ->assertOk()
            ->assertSee('Producto integral')
            ->assertSee('EP006-INTEGRAL')
            ->assertSee('src="'.asset('storage/'.$originalPath).'"', false);

        $this->put(route('admin.products.update', $product), $this->validPayload($newCategory, [
            'name' => 'Producto integral editado',
            'sku' => $product->sku,
            'price' => '145.75',
            'is_active' => '0',
            'image' => $this->fakeImage('reemplazo.webp'),
            'remove_image' => '1',
        ]))->assertSessionHasNoErrors();

        $product->refresh();
        $replacementPath = $product->image;

        $this->assertSame($newCategory->getKey(), $product->category_id);
        $this->assertSame('Producto integral editado', $product->name);
        $this->assertSame('EP006-INTEGRAL', $product->sku);
        $this->assertSame('145.75', $product->price);
        $this->assertFalse($product->is_active);
        $this->assertIsString($replacementPath);
        $this->assertNotSame($originalPath, $replacementPath);
        $this->assertStringStartsWith('products/', $replacementPath);
        Storage::disk('public')->assertMissing($originalPath);
        Storage::disk('public')->assertExists($replacementPath);

        $filters = [
            'q' => 'EP006-INTEG',
            'category_id' => $newCategory->getKey(),
            'status' => 'inactive',
        ];

        $this->get(route('admin.products.index', $filters))
            ->assertOk()
            ->assertSee('Producto integral editado')
            ->assertSee('EP006-INTEGRAL')
            ->assertSee('Categoría destino')
            ->assertSee('src="'.asset('storage/'.$replacementPath).'"', false)
            ->assertViewHas('products', fn ($products): bool => $products->count() === 1
                && $products->first()->is($product));

        $this->get(route('admin.products.index', ['q' => 'integral editado']))
            ->assertOk()
            ->assertSee('EP006-INTEGRAL');

        $this->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('success', 'Producto eliminado correctamente.');

        $trashedProduct = Product::withTrashed()->findOrFail($product->getKey());

        $this->assertSoftDeleted($trashedProduct);
        $this->assertSame($newCategory->getKey(), $trashedProduct->category_id);
        $this->assertSame('EP006-INTEGRAL', $trashedProduct->sku);
        $this->assertSame('145.75', $trashedProduct->price);
        $this->assertFalse($trashedProduct->is_active);
        $this->assertSame($replacementPath, $trashedProduct->image);
        Storage::disk('public')->assertExists($replacementPath);

        $this->get(route('admin.products.index'))
            ->assertOk()
            ->assertDontSee('EP006-INTEGRAL');
        $this->get(route('admin.products.index', $filters))
            ->assertOk()
            ->assertDontSee('EP006-INTEGRAL');
        $this->get(route('admin.products.show', $product->getKey()))->assertNotFound();
        $this->get(route('admin.products.edit', $product->getKey()))->assertNotFound();
        $this->put(
            route('admin.products.update', $product->getKey()),
            $this->validPayload($newCategory, ['sku' => $product->sku]),
        )->assertNotFound();
        $this->delete(route('admin.products.destroy', $product->getKey()))->assertNotFound();
    }

    public function test_product_created_without_image_can_receive_preserve_and_remove_a_jpeg_without_orphans(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.products.store'), $this->validPayload($category, [
                'sku' => 'EP006-IMAGE-STATES',
            ]))
            ->assertSessionHasNoErrors();

        $product = Product::query()->sole();

        $this->assertNull($product->image);
        $this->assertSame([], Storage::disk('public')->allFiles());

        $this->put(route('admin.products.update', $product), $this->validPayload($category, [
            'name' => 'Producto ahora ilustrado',
            'sku' => $product->sku,
            'image' => $this->fakeImage('posterior.jpeg'),
        ]))->assertSessionHasNoErrors();

        $product->refresh();
        $imagePath = $product->image;

        $this->assertIsString($imagePath);
        $this->assertStringStartsWith('products/', $imagePath);
        $this->assertContains(pathinfo($imagePath, PATHINFO_EXTENSION), ['jpg', 'jpeg']);
        Storage::disk('public')->assertExists($imagePath);

        $this->put(route('admin.products.update', $product), $this->validPayload($category, [
            'name' => 'Producto conserva imagen',
            'sku' => $product->sku,
        ]))->assertSessionHasNoErrors();

        $this->assertSame($imagePath, $product->fresh()->image);
        Storage::disk('public')->assertExists($imagePath);

        $attributesBeforeInvalidUpdate = $product->fresh()->getAttributes();

        $this->from(route('admin.products.edit', $product))
            ->put(route('admin.products.update', $product), $this->validPayload($category, [
                'name' => '',
                'sku' => 'SHOULD-NOT-CHANGE',
                'price' => '77.77',
                'is_active' => '0',
                'image' => $this->fakeImage('orphan.png'),
                'remove_image' => '1',
            ]))
            ->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHasErrors('name');

        $this->assertSame($attributesBeforeInvalidUpdate, $product->fresh()->getAttributes());
        $this->assertSame([$imagePath], Storage::disk('public')->allFiles('products'));

        $this->put(route('admin.products.update', $product), $this->validPayload($category, [
            'name' => 'Producto sin imagen otra vez',
            'sku' => $product->sku,
            'remove_image' => '1',
        ]))->assertSessionHasNoErrors();

        $this->assertNull($product->fresh()->image);
        Storage::disk('public')->assertMissing($imagePath);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_category_deletion_integrity_holds_for_every_product_state_and_safe_messages(): void
    {
        $admin = User::factory()->admin()->create();
        $products = collect([
            Product::factory()->active()->create(),
            Product::factory()->inactive()->create(),
            Product::factory()->create(),
        ]);
        $products->last()->delete();

        $this->actingAs($admin);

        foreach ($products as $product) {
            $category = $product->category;
            $response = $this->delete(route('admin.categories.destroy', $category))
                ->assertRedirect(route('admin.categories.index'))
                ->assertSessionHas('error', CategoryDeletionException::hasProducts()->getMessage());
            $message = (string) $response->getSession()->get('error');

            $this->assertStringContainsString('productos asociados', $message);
            $this->assertStringNotContainsString('sql', strtolower($message));
            $this->assertStringNotContainsString('constraint', strtolower($message));
            $this->assertStringNotContainsString('stack trace', strtolower($message));
            $this->assertModelExists($category);
            $this->assertNotNull(Product::withTrashed()->find($product->getKey()));
        }

        $parent = Category::factory()->create();
        $child = Category::factory()->childOf($parent)->create();

        $this->delete(route('admin.categories.destroy', $parent))
            ->assertSessionHas('error', CategoryDeletionException::hasSubcategories()->getMessage());
        $this->assertModelExists($parent);
        $this->assertModelExists($child);

        $leaf = Category::factory()->create();

        $this->delete(route('admin.categories.destroy', $leaf))
            ->assertSessionHas('success', 'Categoría eliminada correctamente.');
        $this->assertModelMissing($leaf);
    }

    public function test_every_product_action_rejects_guests_employees_and_inactive_admins(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();
        $product = Product::factory()->for($category)->create();

        foreach ($this->administrativeRequests($product) as $request) {
            $request()->assertRedirect(route('login'));
        }

        $employee = User::factory()->create();
        $this->actingAs($employee);

        foreach ($this->administrativeRequests($product) as $request) {
            $request()->assertForbidden();
        }

        $inactiveAdmin = User::factory()->admin()->inactive()->create();

        foreach ($this->administrativeRequests($product) as $request) {
            $this->actingAs($inactiveAdmin);
            $request()
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors('email');
            $this->assertGuest();
        }

        $this->assertModelExists($product);
        $this->assertNotSoftDeleted($product);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /**
     * @return array<int, callable(): TestResponse>
     */
    private function administrativeRequests(Product $product): array
    {
        return [
            fn () => $this->get(route('admin.products.index')),
            fn () => $this->get(route('admin.products.create')),
            fn () => $this->post(route('admin.products.store'), $this->validPayload($product->category, [
                'sku' => 'UNAUTHORIZED-STORE',
                'image' => $this->fakeImage('unauthorized-store.jpg'),
            ])),
            fn () => $this->get(route('admin.products.show', $product)),
            fn () => $this->get(route('admin.products.edit', $product)),
            fn () => $this->put(route('admin.products.update', $product), $this->validPayload($product->category, [
                'sku' => $product->sku,
                'image' => $this->fakeImage('unauthorized-update.jpg'),
            ])),
            fn () => $this->delete(route('admin.products.destroy', $product)),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(Category $category, array $overrides = []): array
    {
        return array_merge([
            'category_id' => $category->getKey(),
            'name' => 'Producto integral válido',
            'sku' => 'EP006-'.strtoupper(Str::random(12)),
            'description' => 'Descripción válida para la integración.',
            'price' => '25.50',
            'is_active' => '1',
        ], $overrides);
    }

    private function fakeImage(string $name): UploadedFile
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $contents = match ($extension) {
            'webp' => base64_decode('UklGRh4AAABXRUJQVlA4TBEAAAAvAAAAAAfQ//73v/+BiOh/AAA=', true),
            'jpg', 'jpeg' => base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/EB//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/EB//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/EB//2Q==', true),
            default => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        };

        $this->assertIsString($contents);

        return UploadedFile::fake()->createWithContent($name, $contents);
    }
}
