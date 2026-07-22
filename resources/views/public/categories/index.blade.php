@extends('layouts.public')

@section('title', 'Categorías')
@section('metaDescription', 'Consulta las categorías disponibles de Almacenes al Costo.')

@section('content')
    <section class="public-section-placeholder py-5" aria-labelledby="categories-title">
        <div class="container">
            <header class="mb-4 mb-lg-5">
                <h1 class="display-6 fw-bold mb-3" id="categories-title">Categorías</h1>
                <p class="lead text-body-secondary mb-0">
                    Consulta las categorías disponibles y su organización.
                </p>
            </header>

            @if ($rootCategories->isEmpty())
                <x-public.empty-state
                    class="section-placeholder-card p-4 p-md-5"
                    name="public-categories"
                    title="No hay categorías disponibles"
                    message="Las categorías activas se mostrarán aquí cuando estén disponibles."
                    :heading-level="2"
                />
            @else
                <ul class="list-unstyled d-grid gap-3 mb-0" aria-label="Categorías disponibles">
                    @foreach ($rootCategories as $category)
                        @include('public.categories._category-node', [
                            'category' => $category,
                            'categoriesByParent' => $categoriesByParent,
                            'visitedIds' => [],
                            'depth' => 0,
                            'maxDepth' => $maxDepth,
                        ])
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
@endsection
