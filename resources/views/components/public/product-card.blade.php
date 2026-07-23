@props([
    'name',
    'imageUrl' => null,
    'imageAlt' => null,
    'price' => null,
    'url' => null,
    'detailUrl' => null,
    'description' => null,
    'category' => null,
    'availability' => null,
    'headingLevel' => 3,
])

@php
    $productDetailUrl = $detailUrl ?: $url;
@endphp

<article {{ $attributes->class(['product-card card h-100']) }}>
    <div class="product-card__media">
        @if ($imageUrl && $productDetailUrl)
            <a class="product-card__media-link d-block" href="{{ $productDetailUrl }}">
                <img
                    class="card-img-top img-fluid product-card__image"
                    src="{{ $imageUrl }}"
                    alt="{{ $imageAlt ?: $name }}"
                    width="640"
                    height="480"
                    loading="lazy"
                >
            </a>
        @elseif ($imageUrl)
            <img
                class="card-img-top img-fluid product-card__image"
                src="{{ $imageUrl }}"
                alt="{{ $imageAlt ?: $name }}"
                width="640"
                height="480"
                loading="lazy"
            >
        @else
            <div class="product-card__image-placeholder flex-column gap-2" role="img" aria-label="Imagen no disponible para {{ $name }}">
                <span aria-hidden="true">AC</span>
                <span class="small">Sin imagen</span>
            </div>
        @endif
    </div>

    <div class="card-body d-flex flex-column">
        @if ($category)
            <p class="small text-body-secondary fw-semibold mb-2">{{ $category }}</p>
        @endif

        @if ((int) $headingLevel === 2)
            <h2 class="h5 card-title">
                @if ($productDetailUrl)
                    <a class="stretched-link-target link-dark text-decoration-none" href="{{ $productDetailUrl }}">{{ $name }}</a>
                @else
                    {{ $name }}
                @endif
            </h2>
        @else
            <h3 class="h5 card-title">
                @if ($productDetailUrl)
                    <a class="stretched-link-target link-dark text-decoration-none" href="{{ $productDetailUrl }}">{{ $name }}</a>
                @else
                    {{ $name }}
                @endif
            </h3>
        @endif

        @if ($description)
            <p class="card-text text-body-secondary">{{ $description }}</p>
        @endif

        @if ($price !== null)
            <p class="product-card__price mb-3">{{ $price }}</p>
        @endif

        @if ($availability)
            <p class="mb-3">
                <span class="badge {{ $availability === 'Agotado' ? 'text-bg-secondary' : 'text-bg-success' }}">
                    {{ $availability }}
                </span>
            </p>
        @endif

        @if ($url)
            <a class="btn btn-outline-brand mt-auto align-self-start" href="{{ $url }}">Ver producto</a>
        @endif
    </div>
</article>
