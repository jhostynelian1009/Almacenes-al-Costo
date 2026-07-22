@extends('layouts.admin')

@section('title', 'Árbol de categorías')
@php($breadcrumbs = [
    ['label' => 'Categorías', 'url' => route('admin.categories.index')],
    ['label' => 'Vista de árbol'],
])

@section('content')
    <x-shared.page-header title="Árbol de categorías" subtitle="Consulta la jerarquía completa y el orden dentro de cada nivel.">
        <a class="btn btn-outline-secondary" href="{{ route('admin.categories.index') }}">Volver al listado</a>
    </x-shared.page-header>

    @if ($rootCategories->isEmpty())
        <div class="card shadow-sm">
            <div class="card-body p-4 text-center" role="status">
                <h2 class="h5">No hay categorías para mostrar</h2>
                <p class="text-body-secondary mb-3">Crea la primera categoría para comenzar la jerarquía.</p>
                <a class="btn btn-primary" href="{{ route('admin.categories.create') }}">Crear categoría</a>
            </div>
        </div>
    @else
        <div class="card shadow-sm">
            <div class="card-body">
                <ul class="list-unstyled mb-0" aria-label="Árbol de categorías">
                    @foreach ($rootCategories as $category)
                        @include('admin.categories._tree-node', [
                            'category' => $category,
                            'categoriesByParent' => $categoriesByParent,
                            'visited' => [],
                            'depth' => 0,
                            'maxDepth' => $maxDepth,
                        ])
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
@endsection
