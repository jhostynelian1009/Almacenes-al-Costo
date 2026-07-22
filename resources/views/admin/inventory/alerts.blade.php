@extends('layouts.admin')

@section('title', 'Alertas de inventario')
@php
    $breadcrumbs = [
        ['label' => 'Inventario', 'url' => route('admin.inventory.index')],
        ['label' => 'Alertas'],
    ];
    $stockStatusLabels = [
        'out_of_stock' => 'Agotado',
        'low_stock' => 'Stock bajo',
    ];
    $stockStatusClasses = [
        'out_of_stock' => 'text-bg-danger',
        'low_stock' => 'text-bg-warning',
    ];
@endphp

@section('content')
    <x-shared.page-header title="Alertas de inventario" subtitle="Productos agotados o con disponibilidad igual o inferior al mínimo.">
        <a class="btn btn-outline-secondary" href="{{ route('admin.inventory.index') }}">Volver al inventario</a>
    </x-shared.page-header>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            @if ($inventories->isEmpty())
                <div class="p-4 text-center" role="status">
                    <h2 class="h5">Sin alertas de inventario</h2>
                    <p class="text-body-secondary mb-0">No existen productos con stock bajo o agotado.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Producto</th>
                                <th scope="col">SKU</th>
                                <th scope="col">Categoría</th>
                                <th scope="col" class="text-end">Stock</th>
                                <th scope="col" class="text-end">Reservado</th>
                                <th scope="col" class="text-end">Disponible</th>
                                <th scope="col" class="text-end">Mínimo</th>
                                <th scope="col">Estado del stock</th>
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
                                        <span class="badge {{ $stockStatusClasses[$inventory->stock_status] }}">
                                            {{ $stockStatusLabels[$inventory->stock_status] }}
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
