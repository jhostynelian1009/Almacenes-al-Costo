@props([
    'name',
    'imageUrl' => null,
    'imageAlt' => null,
    'price' => null,
    'url' => null,
    'description' => null,
    'category' => null,
    'availability' => null,
    'headingLevel' => 3,
])

<article {{ $attributes->class(['product-card card h-100']) }}>
    <div class="product-card__media">
        @if ($imageUrl)
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
            <h2 class="h5 card-title">{{ $name }}</h2>
        @else
            <h3 class="h5 card-title">{{ $name }}</h3>
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
