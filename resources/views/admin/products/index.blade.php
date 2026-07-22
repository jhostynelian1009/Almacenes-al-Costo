@extends('layouts.admin')

@section('title', 'Productos')
@php($breadcrumbs = [['label' => 'Productos']])

@section('content')
    <x-shared.page-header title="Productos" subtitle="Administra la información comercial de los productos.">
        <a class="btn btn-primary" href="{{ route('admin.products.create') }}">Crear producto</a>
    </x-shared.page-header>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            @if ($products->isEmpty())
                <div class="p-4 text-center" role="status">
                    <h2 class="h5">No hay productos registrados</h2>
                    <p class="text-body-secondary mb-3">Crea el primer producto para comenzar a administrar el catálogo.</p>
                    <a class="btn btn-primary" href="{{ route('admin.products.create') }}">Crear producto</a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Nombre</th>
                                <th scope="col">SKU</th>
                                <th scope="col">Categoría</th>
                                <th scope="col" class="text-end">Precio</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($products as $product)
                                <tr>
                                    <th scope="row">{{ $product->name }}</th>
                                    <td><code>{{ $product->sku }}</code></td>
                                    <td>{{ $product->category->name }}</td>
                                    <td class="text-end">${{ number_format((float) $product->price, 2) }}</td>
                                    <td>
                                        <span class="badge {{ $product->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $product->is_active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap justify-content-end gap-2">
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.products.show', $product) }}">Ver</a>
                                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.products.edit', $product) }}">Editar</a>
                                            <form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('¿Confirmas que deseas eliminar lógicamente este producto?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Eliminar {{ $product->name }}">Eliminar</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if ($products->hasPages())
        <div class="mt-4">
            {{ $products->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    @endif
@endsection
