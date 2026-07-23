@extends('layouts.public')

@section('title', 'Almacenes al Costo | Inicio')
@section('description', 'Página de inicio y productos destacados de Almacenes al Costo.')

@section('content')
    <section class="home-hero" aria-labelledby="home-title" data-home-section="featured-banners">
        <div class="container py-5 py-lg-6">
            <div class="home-hero__heading mb-4 mb-lg-5">
                <p class="section-eyebrow mb-2">Sitio oficial</p>
                <h1 class="display-3 fw-bold mb-3" id="home-title">Almacenes al Costo</h1>
                <p class="lead mb-4">Consulta aquí los banners y productos destacados publicados por la tienda.</p>
                <a class="btn btn-brand btn-lg" href="{{ route('catalog.index') }}" data-home-cta="catalog">
                    Ir al catálogo
                </a>
            </div>

            <x-public.featured-carousel :banners="$featuredBanners" />
        </div>
    </section>

    <section class="home-products" aria-labelledby="featured-products-title" data-home-section="featured-products">
        <div class="container py-5 py-lg-6">
            <div class="row align-items-end g-3 mb-4">
                <div class="col-lg-8">
                    <x-public.section-heading
                        eyebrow="Selección destacada"
                        title="Productos destacados"
                        heading-id="featured-products-title"
                        description="Los productos seleccionados se mostrarán aquí cuando el catálogo disponga de datos publicados."
                    />
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a class="btn btn-outline-brand" href="{{ route('catalog.index') }}" data-home-cta="featured-products">
                        Ver catálogo
                    </a>
                </div>
            </div>

            @if ($featuredProducts->isEmpty())
                <x-public.empty-state
                    name="featured-products"
                    title="No hay productos destacados disponibles"
                    message="La selección se publicará cuando existan productos reales disponibles para mostrar."
                />
            @else
                <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4">
                    @foreach ($featuredProducts as $product)
                        <div class="col">
                            <x-public.product-card
                                :name="$product['name']"
                                :image-url="$product['image_url'] ?? null"
                                :image-alt="$product['image_alt'] ?? null"
                                :price="$product['price'] ?? null"
                                :url="$product['url'] ?? null"
                                :detail-url="$product['url'] ?? null"
                                :availability="$product['availability'] ?? null"
                            />
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
