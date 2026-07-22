<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_product_without_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.products.store'), $this->validPayload($category))
            ->assertSessionHasNoErrors();

        $this->assertNull(Product::query()->sole()->image);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_admin_can_store_jpg_png_and_webp_as_relative_public_paths(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        foreach (['jpg', 'png', 'webp'] as $extension) {
            $sku = 'IMAGE-'.strtoupper($extension);

            $this->actingAs($admin)
                ->post(route('admin.products.store'), $this->validPayload($category, [
                    'sku' => $sku,
                    'image' => $this->fakeImage("product.{$extension}"),
                ]))
                ->assertSessionHasNoErrors();

            $path = Product::query()->where('sku', $sku)->value('image');

            $this->assertIsString($path);
            $this->assertStringStartsWith('products/', $path);
            $this->assertStringEndsWith(".{$extension}", $path);
            $this->assertFalse(Str::startsWith($path, ['http://', 'https://', 'data:', '/']));
            $this->assertStringNotContainsString('base64', $path);
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_guest_and_employee_cannot_upload_product_images(): void
    {
        Storage::fake('public');
        $category = Category::factory()->create();

        $this->post(route('admin.products.store'), $this->validPayload($category, [
            'image' => $this->fakeImage('guest.jpg'),
        ]))->assertRedirect(route('login'));

        $employee = User::factory()->create();

        $this->actingAs($employee)
            ->post(route('admin.products.store'), $this->validPayload($category, [
                'image' => $this->fakeImage('employee.jpg'),
            ]))
            ->assertForbidden();

        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_svg_gif_pdf_text_disguised_as_image_and_string_paths_are_rejected(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $invalidImages = [
            'svg' => UploadedFile::fake()->createWithContent('active.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'gif' => $this->fakeImage('animated.gif'),
            'pdf' => UploadedFile::fake()->create('document.pdf', 20, 'application/pdf'),
            'fake-image' => UploadedFile::fake()
                ->createWithContent('renamed.jpg', 'plain text, not an image')
                ->mimeType('text/plain'),
            'string-path' => 'products/user-provided.jpg',
        ];

        foreach ($invalidImages as $case => $image) {
            $sku = "INVALID-{$case}";
            $response = $this->actingAs($admin)
                ->post(route('admin.products.store'), $this->validPayload($category, [
                    'sku' => $sku,
                    'image' => $image,
                ]));

            $this->assertDatabaseMissing('products', ['sku' => $sku]);
            $response->assertSessionHasErrors('image');
        }

        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_image_larger_than_two_megabytes_and_multiple_files_are_rejected(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.products.store'), $this->validPayload($category, [
                'image' => $this->fakeImage('large.jpg')->size(2049),
            ]))
            ->assertSessionHasErrors('image');

        $this->post(route('admin.products.store'), $this->validPayload($category, [
            'image' => [
                $this->fakeImage('first.jpg'),
                $this->fakeImage('second.jpg'),
            ],
        ]))->assertSessionHasErrors('image');

        $this->assertDatabaseCount('products', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_forms_are_multipart_and_expose_accessible_file_controls(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.products.create'))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('type="file"', false)
            ->assertSee('name="image"', false)
            ->assertSee('for="image"', false)
            ->assertSee('accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"', false)
            ->assertSee('Tamaño máximo: 2 MB')
            ->assertSee('Sin imagen');

        $this->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false);
    }

    public function test_detail_and_index_render_existing_image_with_accessible_alternative_text(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $path = 'products/existing.jpg';
        Storage::disk('public')->put($path, 'image bytes');
        $product = Product::factory()->create([
            'name' => 'Producto con imagen',
            'image' => $path,
        ]);
        $url = Storage::disk('public')->url($path);

        $this->actingAs($admin)
            ->get(route('admin.products.show', $product))
            ->assertOk()
            ->assertSee('src="'.$url.'"', false)
            ->assertSee('alt="Imagen principal de Producto con imagen"', false);

        $this->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('src="'.$url.'"', false)
            ->assertSee('alt="Imagen principal de Producto con imagen"', false);
    }

    public function test_list_displays_distinct_empty_and_missing_image_states(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        Product::factory()->create(['name' => 'Sin referencia', 'image' => null]);
        Product::factory()->create(['name' => 'Referencia faltante', 'image' => 'products/missing.jpg']);

        $this->actingAs($admin)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Sin imagen')
            ->assertSee('Imagen no disponible')
            ->assertDontSee('src="'.Storage::disk('public')->url('products/missing.jpg').'"', false);
    }

    public function test_admin_can_replace_image_after_product_update_succeeds(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $oldPath = 'products/old.jpg';
        Storage::disk('public')->put($oldPath, 'old image');
        $product = Product::factory()->create(['image' => $oldPath]);

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), $this->validPayload($product->category, [
                'sku' => $product->sku,
                'image' => $this->fakeImage('replacement.png'),
            ]))
            ->assertSessionHasNoErrors();

        $newPath = $product->fresh()->image;

        $this->assertNotSame($oldPath, $newPath);
        $this->assertStringStartsWith('products/', $newPath);
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_update_without_image_action_preserves_existing_path_and_file(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $path = 'products/preserved.jpg';
        Storage::disk('public')->put($path, 'preserved image');
        $product = Product::factory()->create(['image' => $path]);

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), $this->validPayload($product->category, [
                'name' => 'Producto actualizado sin imagen',
                'sku' => $product->sku,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($path, $product->fresh()->image);
        Storage::disk('public')->assertExists($path);
    }

    public function test_admin_can_explicitly_remove_image_and_physical_file(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $path = 'products/remove.jpg';
        Storage::disk('public')->put($path, 'image to remove');
        $product = Product::factory()->create(['image' => $path]);

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), $this->validPayload($product->category, [
                'sku' => $product->sku,
                'remove_image' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull($product->fresh()->image);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_new_image_has_priority_when_remove_is_requested_simultaneously(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $oldPath = 'products/priority-old.jpg';
        Storage::disk('public')->put($oldPath, 'old image');
        $product = Product::factory()->create(['image' => $oldPath]);

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), $this->validPayload($product->category, [
                'sku' => $product->sku,
                'image' => $this->fakeImage('priority-new.webp'),
                'remove_image' => '1',
            ]))
            ->assertSessionHasNoErrors();

        $newPath = $product->fresh()->image;

        $this->assertNotNull($newPath);
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_missing_file_does_not_break_detail_or_edit_and_can_be_removed(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['image' => 'products/missing.jpg']);

        $this->actingAs($admin)
            ->get(route('admin.products.show', $product))
            ->assertOk()
            ->assertSee('La imagen registrada no está disponible')
            ->assertDontSee('<img', false);

        $this->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('Puedes reemplazarla o retirar su referencia')
            ->assertSee('name="remove_image"', false);

        $this->put(route('admin.products.update', $product), $this->validPayload($product->category, [
            'sku' => $product->sku,
            'remove_image' => '1',
        ]))->assertSessionHasNoErrors();

        $this->assertNull($product->fresh()->image);
    }

    public function test_soft_delete_keeps_image_path_and_physical_file(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $path = 'products/soft-delete.jpg';
        Storage::disk('public')->put($path, 'restorable image');
        $product = Product::factory()->create(['image' => $path]);

        $this->actingAs($admin)
            ->delete(route('admin.products.destroy', $product))
            ->assertSessionHasNoErrors();

        $trashedProduct = Product::withTrashed()->findOrFail($product->getKey());

        $this->assertSoftDeleted($trashedProduct);
        $this->assertSame($path, $trashedProduct->image);
        Storage::disk('public')->assertExists($path);
    }

    public function test_image_management_adds_no_routes_or_public_upload_endpoints(): void
    {
        $productRouteNames = collect(Route::getRoutes()->getRoutesByName())
            ->keys()
            ->filter(fn (string $name): bool => str_starts_with($name, 'admin.products.'))
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'admin.products.create',
            'admin.products.destroy',
            'admin.products.edit',
            'admin.products.index',
            'admin.products.show',
            'admin.products.store',
            'admin.products.update',
        ], $productRouteNames);
        $this->assertFalse(Route::has('admin.products.image.destroy'));
        $this->assertFalse(Route::has('admin.products.force-delete'));

        $publicUploadRoutes = collect(Route::getRoutes())
            ->filter(fn ($route): bool => ! str_starts_with($route->uri(), 'admin/'))
            ->filter(fn ($route): bool => str_contains($route->uri(), 'image'));

        $this->assertCount(0, $publicUploadRoutes);
    }

    public function test_updating_one_product_does_not_modify_another_products_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        Storage::disk('public')->put('products/first.jpg', 'first');
        Storage::disk('public')->put('products/second.jpg', 'second');
        $first = Product::factory()->create(['image' => 'products/first.jpg']);
        $second = Product::factory()->create(['image' => 'products/second.jpg']);

        $this->actingAs($admin)
            ->put(route('admin.products.update', $first), $this->validPayload($first->category, [
                'sku' => $first->sku,
                'image' => $this->fakeImage('first-new.jpg'),
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('products/second.jpg', $second->fresh()->image);
        Storage::disk('public')->assertExists('products/second.jpg');
    }

    public function test_invalid_update_stores_nothing_and_preserves_existing_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $path = 'products/validation-preserved.jpg';
        Storage::disk('public')->put($path, 'existing image');
        $product = Product::factory()->create(['image' => $path]);

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), $this->validPayload($product->category, [
                'name' => '',
                'sku' => $product->sku,
                'image' => $this->fakeImage('valid-but-request-invalid.png'),
                'remove_image' => '1',
            ]))
            ->assertSessionHasErrors('name');

        $this->assertSame($path, $product->fresh()->image);
        $this->assertSame([$path], Storage::disk('public')->allFiles('products'));
    }

    public function test_new_file_is_cleaned_if_database_update_fails_and_original_error_is_rethrown(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $oldPath = 'products/database-failure-old.jpg';
        Storage::disk('public')->put($oldPath, 'old image');
        $product = Product::factory()->create(['image' => $oldPath]);
        $eventName = 'eloquent.updating: '.Product::class;
        Event::listen($eventName, fn (): never => throw new RuntimeException('Forced persistence failure.'));

        try {
            $this->actingAs($admin)
                ->withoutExceptionHandling()
                ->put(route('admin.products.update', $product), $this->validPayload($product->category, [
                    'sku' => $product->sku,
                    'image' => $this->fakeImage('orphan.png'),
                ]));

            $this->fail('The persistence exception should be rethrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced persistence failure.', $exception->getMessage());
        } finally {
            Event::forget($eventName);
        }

        $this->assertSame($oldPath, $product->fresh()->image);
        $this->assertSame([$oldPath], Storage::disk('public')->allFiles('products'));
    }

    public function test_service_never_deletes_paths_outside_managed_product_directory(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('other/sensitive.txt', 'sensitive');
        $service = app(ProductImageService::class);

        $service->delete('other/sensitive.txt');
        $service->delete('../other/sensitive.txt');
        $service->delete('/absolute/path.txt');

        Storage::disk('public')->assertExists('other/sensitive.txt');
        $this->assertFalse($service->exists('other/sensitive.txt'));
        $this->assertNull($service->url('other/sensitive.txt'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(Category $category, array $overrides = []): array
    {
        return array_merge([
            'category_id' => $category->getKey(),
            'name' => 'Producto con imagen',
            'sku' => 'IMG-'.strtoupper(Str::random(12)),
            'description' => 'Descripción válida',
            'price' => '25.50',
            'is_active' => '1',
        ], $overrides);
    }

    private function fakeImage(string $name): UploadedFile
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $contents = match ($extension) {
            'gif' => base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==', true),
            'webp' => base64_decode('UklGRh4AAABXRUJQVlA4TBEAAAAvAAAAAAfQ//73v/+BiOh/AAA=', true),
            'jpg', 'jpeg' => base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/EB//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/EB//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/EB//2Q==', true),
            default => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        };

        $this->assertIsString($contents);

        return UploadedFile::fake()->createWithContent($name, $contents);
    }
}
