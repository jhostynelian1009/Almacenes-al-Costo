@extends('layouts.admin')

@section('title', 'Inventario de '.$inventory->product->name)
@php
    $product = $inventory->product;
    $productDeleted = $product->trashed();
    $breadcrumbs = [
        ['label' => 'Inventario', 'url' => route('admin.inventory.index')],
        ['label' => $product->name],
    ];
    $movementLabels = [
        'entry' => 'Entrada',
        'exit' => 'Salida',
        'adjustment' => 'Ajuste',
        'reserve' => 'Reserva',
        'release' => 'Liberación',
    ];
@endphp

@section('content')
    <x-shared.page-header :title="'Inventario de '.$product->name" subtitle="Existencias actuales e historial de movimientos." />

    @if ($productDeleted)
        <div class="alert alert-warning" role="status">
            <strong>Producto eliminado.</strong> El historial permanece disponible, pero no se permiten movimientos nuevos.
        </div>
    @endif

    <section class="card shadow-sm mb-4" aria-labelledby="inventory-summary-heading">
        <div class="card-header">
            <h2 class="h5 mb-0" id="inventory-summary-heading">Resumen de existencias</h2>
        </div>
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-4 col-lg-3">Producto</dt>
                <dd class="col-sm-8 col-lg-9">{{ $product->name }}</dd>

                <dt class="col-sm-4 col-lg-3">SKU</dt>
                <dd class="col-sm-8 col-lg-9"><code>{{ $product->sku }}</code></dd>

                <dt class="col-sm-4 col-lg-3">Categoría</dt>
                <dd class="col-sm-8 col-lg-9">{{ $product->category->name }}</dd>

                <dt class="col-sm-4 col-lg-3">Estado</dt>
                <dd class="col-sm-8 col-lg-9">
                    @if ($productDeleted)
                        Producto eliminado
                    @else
                        {{ $product->is_active ? 'Activo' : 'Inactivo' }}
                    @endif
                </dd>

                <dt class="col-sm-4 col-lg-3">Stock total</dt>
                <dd class="col-sm-8 col-lg-9">{{ $inventory->stock }}</dd>

                <dt class="col-sm-4 col-lg-3">Stock reservado</dt>
                <dd class="col-sm-8 col-lg-9">{{ $inventory->reserved_stock }}</dd>

                <dt class="col-sm-4 col-lg-3">Stock disponible</dt>
                <dd class="col-sm-8 col-lg-9">{{ $inventory->available_stock }}</dd>

                <dt class="col-sm-4 col-lg-3">Stock mínimo</dt>
                <dd class="col-sm-8 col-lg-9">{{ $inventory->min_stock }}</dd>
            </dl>
        </div>
    </section>

    @unless ($productDeleted)
        <section class="card shadow-sm mb-4" aria-labelledby="inventory-actions-heading">
            <div class="card-body">
                <h2 class="h5" id="inventory-actions-heading">Registrar movimiento</h2>
                <p class="text-body-secondary">Selecciona la operación manual que deseas registrar.</p>
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($movementLabels as $type => $label)
                        <a class="btn btn-outline-primary" href="{{ route('admin.inventory.movements.create', [$inventory, 'type' => $type]) }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endunless

    <section class="card shadow-sm" aria-labelledby="movement-history-heading">
        <div class="card-header">
            <h2 class="h5 mb-0" id="movement-history-heading">Historial de movimientos</h2>
        </div>
        <div class="card-body p-0">
            @if ($movements->isEmpty())
                <div class="p-4 text-center" role="status">
                    <h3 class="h6">Sin movimientos registrados</h3>
                    <p class="text-body-secondary mb-0">El historial comenzará con la primera operación real.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Tipo</th>
                                <th scope="col">Cambio de stock</th>
                                <th scope="col">Cambio reservado</th>
                                <th scope="col">Stock anterior → posterior</th>
                                <th scope="col">Reserva anterior → posterior</th>
                                <th scope="col">Motivo</th>
                                <th scope="col">Actor</th>
                                <th scope="col">Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($movements as $movement)
                                @php
                                    $stockDelta = $movement->stock_delta > 0 ? '+'.$movement->stock_delta : (string) $movement->stock_delta;
                                    $reservedDelta = $movement->reserved_delta > 0 ? '+'.$movement->reserved_delta : (string) $movement->reserved_delta;
                                @endphp
                                <tr>
                                    <th scope="row">{{ $movementLabels[$movement->type] }}</th>
                                    <td>{{ $stockDelta }}</td>
                                    <td>{{ $reservedDelta }}</td>
                                    <td>{{ $movement->stock_before }} → {{ $movement->stock_after }}</td>
                                    <td>{{ $movement->reserved_before }} → {{ $movement->reserved_after }}</td>
                                    <td>{{ $movement->reason ?? 'Sin motivo registrado' }}</td>
                                    <td>{{ $movement->creator?->name ?? 'Sistema' }}</td>
                                    <td>{{ $movement->created_at?->format('d/m/Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>

    @if ($movements->hasPages())
        <div class="mt-4">
            {{ $movements->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    @endif

    <p class="mt-4 mb-0"><a href="{{ route('admin.inventory.index') }}">Volver al inventario</a></p>
@endsection
