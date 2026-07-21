@props(['banners'])

@if (count($banners) === 0)
    <x-public.empty-state
        class="home-featured-carousel__empty"
        name="featured-banners"
        title="Banners destacados aún no disponibles"
        message="Este espacio mostrará información destacada cuando exista contenido comercial confirmado."
    />
@else
    <div class="carousel slide home-featured-carousel" id="homeFeaturedCarousel" aria-label="Banners destacados">
        <div class="carousel-indicators">
            @foreach ($banners as $banner)
                <button
                    class="{{ $loop->first ? 'active' : '' }}"
                    type="button"
                    data-bs-target="#homeFeaturedCarousel"
                    data-bs-slide-to="{{ $loop->index }}"
                    @if ($loop->first) aria-current="true" @endif
                    aria-label="Mostrar banner {{ $loop->iteration }}"
                ></button>
            @endforeach
        </div>

        <div class="carousel-inner">
            @foreach ($banners as $banner)
                <article class="carousel-item {{ $loop->first ? 'active' : '' }}">
                    <img
                        class="d-block w-100 home-featured-carousel__image"
                        src="{{ $banner['image_url'] }}"
                        alt="{{ $banner['image_alt'] }}"
                        width="1200"
                        height="560"
                        @if (! $loop->first) loading="lazy" @endif
                    >
                    <div class="carousel-caption">
                        <h2 class="h3">{{ $banner['title'] }}</h2>
                        @if (! empty($banner['message']))
                            <p>{{ $banner['message'] }}</p>
                        @endif
                        @if (! empty($banner['cta_url']) && ! empty($banner['cta_label']))
                            <a class="btn btn-brand" href="{{ $banner['cta_url'] }}">{{ $banner['cta_label'] }}</a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        @if (count($banners) > 1)
            <button class="carousel-control-prev" type="button" data-bs-target="#homeFeaturedCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Banner anterior</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#homeFeaturedCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Banner siguiente</span>
            </button>
        @endif
    </div>
@endif
