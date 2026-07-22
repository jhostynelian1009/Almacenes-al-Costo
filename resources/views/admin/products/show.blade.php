@extends('layouts.admin')

@section('title', $product->name)
@php($breadcrumbs = [
    ['label' => 'Productos', 'url' => route('admin.products.index')],
    ['label' => $product->name],
])

@section('content')
    <x-shared.page-header :title="$product->name" subtitle="Detalle administrativo del producto.">
        <a class="btn btn-primary" href="{{ route('admin.products.edit', $product) }}">Editar producto</a>
    </x-shared.page-header>

    <div class="card shadow-sm">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-4 col-lg-3">Nombre</dt>
                <dd class="col-sm-8 col-lg-9">{{ $product->name }}</dd>

                <dt class="col-sm-4 col-lg-3">SKU</dt>
                <dd class="col-sm-8 col-lg-9"><code>{{ $product->sku }}</code></dd>

                <dt class="col-sm-4 col-lg-3">Categoría</dt>
                <dd class="col-sm-8 col-lg-9">{{ $product->category->name }}</dd>

                <dt class="col-sm-4 col-lg-3">Descripción</dt>
                <dd class="col-sm-8 col-lg-9">{{ $product->description ?? 'Sin descripción' }}</dd>

                <dt class="col-sm-4 col-lg-3">Precio</dt>
                <dd class="col-sm-8 col-lg-9">${{ number_format((float) $product->price, 2) }}</dd>

                <dt class="col-sm-4 col-lg-3">Estado</dt>
                <dd class="col-sm-8 col-lg-9">{{ $product->is_active ? 'Activo' : 'Inactivo' }}</dd>

                <dt class="col-sm-4 col-lg-3">Creado</dt>
                <dd class="col-sm-8 col-lg-9">{{ $product->created_at?->format('d/m/Y H:i') }}</dd>

                <dt class="col-sm-4 col-lg-3">Actualizado</dt>
                <dd class="col-sm-8 col-lg-9">{{ $product->updated_at?->format('d/m/Y H:i') }}</dd>
            </dl>
        </div>
        <div class="card-footer bg-transparent d-flex flex-wrap gap-3">
            <a href="{{ route('admin.products.index') }}">Volver a productos</a>
        </div>
    </div>
@endsection
