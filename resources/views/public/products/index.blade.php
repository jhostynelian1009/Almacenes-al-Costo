@extends('layouts.public')

@section('title', 'Catálogo | Almacenes al Costo')
@section('description', 'Consulta el catálogo público de productos disponibles en Almacenes al Costo.')

@section('content')
    <section class="public-section-placeholder py-5" aria-labelledby="catalog-title">
        <div class="container">
            <header class="mb-4">
                <p class="section-eyebrow mb-2">Productos</p>
                <h1 class="display-6 fw-bold mb-3" id="catalog-title">Catálogo</h1>
                <p class="lead text-body-secondary mb-0">
                    Consulta los productos publicados y su disponibilidad actual.
                </p>
            </header>

            <form class="section-placeholder-card p-3 p-md-4 mb-4" method="GET" action="{{ route('catalog.index') }}" aria-label="Buscar y filtrar catálogo">
                <div class="row g-3 align-items-end">
                    <div class="col-md-6 col-lg-5">
                        <label class="form-label fw-semibold" for="catalog-search">Buscar productos</label>
                        <input
                            class="form-control"
                            id="catalog-search"
                            name="q"
                            type="search"
                            value="{{ $searchQuery }}"
                            maxlength="100"
                            placeholder="Nombre o descripción"
                        >
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <label class="form-label fw-semibold" for="catalog-category">Categoría</label>
                        <select class="form-select" id="catalog-category" name="category">
                            <option value="">Todas las categorías</option>
                            @foreach ($publicCategories as $category)
                                <option value="{{ $category->slug }}" @selected($selectedCategory?->is($category))>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3 d-flex flex-wrap gap-2">
                        <button class="btn btn-brand" type="submit">Buscar</button>
                        @if ($searchQuery || $selectedCategory)
                            <a class="btn btn-outline-brand" href="{{ route('catalog.index') }}">Limpiar</a>
                        @endif
                    </div>
                </div>
            </form>

            @if ($selectedCategory)
                <p class="mb-4" role="status">
                    Categoría seleccionada: <strong>{{ $selectedCategory->name }}</strong>
                </p>
            @endif

            @if ($searchQuery)
                <p class="mb-4" role="status">
                    Resultados para: <strong>{{ $searchQuery }}</strong>
                </p>
            @endif

            @if ($products->isEmpty())
                <x-public.empty-state
                    class="section-placeholder-card"
                    name="public-products"
                    title="Sin productos disponibles"
                    :message="$searchQuery
                        ? 'No se encontraron productos para tu búsqueda.'
                        : ($selectedCategory
                            ? 'No hay productos disponibles en esta categoría.'
                            : 'No hay productos disponibles en este momento.')"
                    :heading-level="2"
                />
            @else
                <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-4" aria-label="Productos del catálogo">
                    @foreach ($products as $product)
                        @php
                            $hasPublicImage = filled($product->image)
                                && Illuminate\Support\Facades\Storage::disk('public')->exists($product->image);
                            $imageUrl = $hasPublicImage ? asset('storage/'.$product->image) : null;
                            $availability = $product->inventory->available_stock > 0 ? 'Disponible' : 'Agotado';
                            $detailUrl = route('catalog.show', $product->slug);
                        @endphp
                        <div class="col">
                            <x-public.product-card
                                :name="$product->name"
                                :description="filled($product->description) ? Illuminate\Support\Str::limit($product->description, 140) : null"
                                :category="$product->category->name"
                                :image-url="$imageUrl"
                                :image-alt="$product->name"
                                :price="'$ '.number_format((float) $product->price, 2, '.', ',')"
                                :availability="$availability"
                                :detail-url="$detailUrl"
                                :url="$detailUrl"
                                :heading-level="2"
                            />
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($products->hasPages())
                <div class="mt-4">
                    {{ $products->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </section>
@endsection
