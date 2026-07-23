@extends('layouts.public')

@section('title', $product->name.' | Almacenes al Costo')
@section('description', filled($product->description) ? Illuminate\Support\Str::limit(strip_tags($product->description), 155) : 'Detalle del producto '.$product->name.' en Almacenes al Costo.')

@section('content')
    @php
        $hasPublicImage = filled($product->image)
            && Illuminate\Support\Facades\Storage::disk('public')->exists($product->image);
        $imageUrl = $hasPublicImage ? asset('storage/'.$product->image) : null;
        $availability = $product->inventory->available_stock > 0 ? 'Disponible' : 'Agotado';
    @endphp

    <section class="public-section-placeholder py-5" aria-labelledby="product-detail-title">
        <div class="container">
            <nav class="mb-4" aria-label="Navegación del producto">
                <a class="link-brand" href="{{ route('catalog.index') }}">Volver al catálogo</a>
            </nav>

            <div class="row g-4 g-lg-5 align-items-start">
                <div class="col-lg-5">
                    @if ($imageUrl)
                        <img
                            class="img-fluid rounded border w-100 product-detail__image"
                            src="{{ $imageUrl }}"
                            alt="{{ $product->name }}"
                            width="640"
                            height="480"
                        >
                    @else
                        <div class="product-card__image-placeholder flex-column gap-2 rounded border" role="img" aria-label="Sin imagen para {{ $product->name }}">
                            <span aria-hidden="true">AC</span>
                            <span class="small">Sin imagen</span>
                        </div>
                    @endif
                </div>

                <div class="col-lg-7">
                    <header class="mb-4">
                        <p class="section-eyebrow mb-2">Producto</p>
                        <h1 class="display-6 fw-bold mb-3" id="product-detail-title">{{ $product->name }}</h1>
                        <p class="mb-2">
                            <span class="text-body-secondary">Categoría:</span>
                            <a class="link-brand" href="{{ route('catalog.index', ['category' => $product->category->slug]) }}">
                                {{ $product->category->name }}
                            </a>
                        </p>
                        <p class="product-card__price h4 mb-3">$ {{ number_format((float) $product->price, 2, '.', ',') }}</p>
                        <p class="mb-0">
                            <span class="badge {{ $availability === 'Agotado' ? 'text-bg-secondary' : 'text-bg-success' }}">
                                {{ $availability }}
                            </span>
                        </p>
                    </header>

                    @if (filled($product->description))
                        <section aria-labelledby="product-description-title">
                            <h2 class="h5 mb-3" id="product-description-title">Descripción</h2>
                            <p class="text-body-secondary mb-0">{{ $product->description }}</p>
                        </section>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
