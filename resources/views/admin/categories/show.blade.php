@extends('layouts.admin')

@section('title', $category->name)
@php($breadcrumbs = [
    ['label' => 'Categorías', 'url' => route('admin.categories.index')],
    ['label' => $category->name],
])

@section('content')
    <x-shared.page-header :title="$category->name" subtitle="Detalle de la categoría.">
        <a class="btn btn-primary" href="{{ route('admin.categories.edit', $category) }}">Editar categoría</a>
    </x-shared.page-header>

    <div class="card shadow-sm">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-4 col-lg-3">Nombre</dt>
                <dd class="col-sm-8 col-lg-9">{{ $category->name }}</dd>

                <dt class="col-sm-4 col-lg-3">Slug</dt>
                <dd class="col-sm-8 col-lg-9"><code>{{ $category->slug }}</code></dd>

                <dt class="col-sm-4 col-lg-3">Descripción</dt>
                <dd class="col-sm-8 col-lg-9">{{ $category->description ?? 'Sin descripción' }}</dd>

                <dt class="col-sm-4 col-lg-3">Categoría padre</dt>
                <dd class="col-sm-8 col-lg-9">{{ $category->parent?->name ?? 'Principal' }}</dd>

                <dt class="col-sm-4 col-lg-3">Estado</dt>
                <dd class="col-sm-8 col-lg-9">{{ $category->is_active ? 'Activa' : 'Inactiva' }}</dd>

                <dt class="col-sm-4 col-lg-3">Orden de visualización</dt>
                <dd class="col-sm-8 col-lg-9">{{ $category->display_order }}</dd>

                <dt class="col-sm-4 col-lg-3">Creada</dt>
                <dd class="col-sm-8 col-lg-9">{{ $category->created_at?->format('d/m/Y H:i') }}</dd>

                <dt class="col-sm-4 col-lg-3">Actualizada</dt>
                <dd class="col-sm-8 col-lg-9">{{ $category->updated_at?->format('d/m/Y H:i') }}</dd>
            </dl>
        </div>
        <div class="card-footer bg-transparent">
            <a href="{{ route('admin.categories.index') }}">Volver a categorías</a>
        </div>
    </div>
@endsection
