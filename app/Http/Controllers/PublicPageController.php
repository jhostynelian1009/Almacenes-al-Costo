<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PublicPageController extends Controller
{
    public function home(): View
    {
        $visibleCategoryIds = PublicProductController::resolveVisibleCategoryIds();

        $featuredProducts = Product::query()
            ->select(['id', 'category_id', 'name', 'slug', 'price', 'image'])
            ->where('is_active', true)
            ->whereIn('category_id', $visibleCategoryIds)
            ->whereHas('inventory')
            ->with([
                'inventory:id,product_id,stock,reserved_stock',
            ])
            ->get()
            ->sortBy([
                fn (Product $product): int => $product->inventory->available_stock > 0 ? 0 : 1,
                ['name', 'asc'],
                ['id', 'asc'],
            ])
            ->take(8)
            ->map(fn (Product $product): array => $this->presentFeaturedProduct($product))
            ->values();

        return view('public.home', [
            'featuredBanners' => $this->featuredBanners(),
            'featuredProducts' => $featuredProducts,
        ]);
    }

    public function promotions(): View
    {
        return $this->section(
            title: 'Promociones',
            description: 'Consulta el acceso inicial a promociones de Almacenes al Costo.',
            message: 'Las promociones vigentes se mostrarán en una funcionalidad posterior.',
        );
    }

    public function information(): View
    {
        return $this->section(
            title: 'Información',
            description: 'Información institucional de Almacenes al Costo.',
            message: 'La información comercial y los canales oficiales están pendientes de publicación.',
        );
    }

    /**
     * @return Collection<int, array<string, string|null>>
     */
    private function featuredBanners(): Collection
    {
        return collect([
            [
                'image_url' => asset('images/public/banner-catalogo.svg'),
                'image_alt' => 'Promoción del catálogo de Almacenes al Costo',
                'title' => 'Precios al costo en todo el catálogo',
                'message' => 'Explora productos disponibles y consulta su disponibilidad actual.',
                'cta_url' => route('catalog.index'),
                'cta_label' => 'Ver catálogo',
            ],
            [
                'image_url' => asset('images/public/banner-categorias.svg'),
                'image_alt' => 'Promoción de categorías de Almacenes al Costo',
                'title' => 'Encuentra lo que necesitas por categoría',
                'message' => 'Navega por nuestras categorías activas y descubre nuevas opciones.',
                'cta_url' => route('categories.index'),
                'cta_label' => 'Ver categorías',
            ],
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    private function presentFeaturedProduct(Product $product): array
    {
        $hasPublicImage = filled($product->image)
            && Storage::disk('public')->exists($product->image);

        return [
            'name' => $product->name,
            'image_url' => $hasPublicImage ? asset('storage/'.$product->image) : null,
            'image_alt' => $product->name,
            'price' => '$ '.number_format((float) $product->price, 2, '.', ','),
            'url' => route('catalog.show', $product->slug),
            'availability' => $product->inventory->available_stock > 0 ? 'Disponible' : 'Agotado',
        ];
    }

    private function section(string $title, string $description, string $message): View
    {
        return view('public.section', compact('title', 'description', 'message'));
    }
}
