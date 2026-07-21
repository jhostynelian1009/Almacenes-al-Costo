@extends('layouts.public')

@section('title', 'Almacenes al Costo | Inicio')
@section('description', 'Sitio público de Almacenes al Costo.')

@section('content')
    <section class="public-hero" aria-labelledby="home-title">
        <div class="container py-5 py-lg-6">
            <div class="row align-items-center g-4 g-lg-5">
                <div class="col-lg-7">
                    <p class="section-eyebrow mb-2">Sitio oficial</p>
                    <h1 class="display-3 fw-bold" id="home-title">Almacenes al Costo</h1>
                    <p class="lead col-xl-9">Explora la estructura inicial de nuestra experiencia pública.</p>
                    <div class="d-flex flex-column flex-sm-row gap-3 mt-4">
                        <a class="btn btn-brand btn-lg" href="{{ route('catalog.index') }}">Ir al catálogo</a>
                        <a class="btn btn-outline-brand btn-lg" href="{{ route('information') }}">Información institucional</a>
                    </div>
                </div>
                <div class="col-lg-5">
                    <aside class="hero-status-card p-4 p-md-5" aria-labelledby="availability-title">
                        <h2 class="h4" id="availability-title">Contenido en preparación</h2>
                        <p class="mb-0">El catálogo y las funciones comerciales se incorporarán en entregas posteriores.</p>
                    </aside>
                </div>
            </div>
        </div>
    </section>

    <section class="container py-5" aria-labelledby="navigation-title">
        <div class="row align-items-end g-3 mb-4">
            <div class="col-lg-8">
                <p class="section-eyebrow mb-2">Navegación pública</p>
                <h2 class="display-6 fw-bold mb-0" id="navigation-title">Encuentra cada sección fácilmente</h2>
            </div>
        </div>
        <div class="row g-3">
            @foreach ([
                ['Catálogo', 'Acceso inicial a la futura oferta de productos.', 'catalog.index'],
                ['Categorías', 'Acceso inicial a la futura organización del catálogo.', 'categories.index'],
                ['Promociones', 'Espacio reservado para promociones confirmadas.', 'promotions.index'],
            ] as [$title, $text, $route])
                <div class="col-md-4">
                    <article class="public-navigation-card p-4">
                        <h3 class="h5">{{ $title }}</h3>
                        <p>{{ $text }}</p>
                        <a class="stretched-link" href="{{ route($route) }}">Visitar {{ strtolower($title) }}</a>
                    </article>
                </div>
            @endforeach
        </div>
    </section>
@endsection
