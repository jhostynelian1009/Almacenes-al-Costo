@props([
    'name',
    'imageUrl' => null,
    'imageAlt' => null,
    'price' => null,
    'url' => null,
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
            <div class="product-card__image-placeholder" role="img" aria-label="Imagen no disponible para {{ $name }}">
                <span aria-hidden="true">AC</span>
            </div>
        @endif
    </div>

    <div class="card-body d-flex flex-column">
        <h3 class="h5 card-title">{{ $name }}</h3>

        @if ($price !== null)
            <p class="product-card__price mb-3">{{ $price }}</p>
        @endif

        @if ($url)
            <a class="btn btn-outline-brand mt-auto align-self-start" href="{{ $url }}">Ver producto</a>
        @endif
    </div>
</article>
