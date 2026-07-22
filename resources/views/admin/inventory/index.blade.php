@extends('layouts.admin')

@section('title', 'Inventario')
@php($breadcrumbs = [['label' => 'Inventario']])

@section('content')
    <x-shared.page-header title="Inventario" subtitle="Consulta las existencias actuales de los productos." />

    <div class="card shadow-sm">
        <div class="card-body p-0">
            @if ($inventories->isEmpty())
                <div class="p-4 text-center" role="status">
                    <h2 class="h5">No hay inventarios disponibles</h2>
                    <p class="text-body-secondary mb-0">
                        Los inventarios se crean automáticamente cuando se registra un producto.
                    </p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Producto</th>
                                <th scope="col">SKU</th>
                                <th scope="col">Categoría</th>
                                <th scope="col" class="text-end">Stock total</th>
                                <th scope="col" class="text-end">Reservado</th>
                                <th scope="col" class="text-end">Disponible</th>
                                <th scope="col" class="text-end">Mínimo</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($inventories as $inventory)
                                <tr>
                                    <th scope="row">{{ $inventory->product->name }}</th>
                                    <td><code>{{ $inventory->product->sku }}</code></td>
                                    <td>{{ $inventory->product->category->name }}</td>
                                    <td class="text-end">{{ $inventory->stock }}</td>
                                    <td class="text-end">{{ $inventory->reserved_stock }}</td>
                                    <td class="text-end">{{ $inventory->available_stock }}</td>
                                    <td class="text-end">{{ $inventory->min_stock }}</td>
                                    <td>
                                        <span class="badge {{ $inventory->product->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $inventory->product->is_active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.inventory.show', $inventory) }}">
                                            Ver detalle
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if ($inventories->hasPages())
        <div class="mt-4">
            {{ $inventories->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    @endif
@endsection
