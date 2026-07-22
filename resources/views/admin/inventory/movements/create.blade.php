@extends('layouts.admin')

@section('title', 'Registrar movimiento')
@php
    $product = $inventory->product;
    $breadcrumbs = [
        ['label' => 'Inventario', 'url' => route('admin.inventory.index')],
        ['label' => $product->name, 'url' => route('admin.inventory.show', $inventory)],
        ['label' => 'Registrar movimiento'],
    ];
@endphp

@section('content')
    <x-shared.page-header title="Registrar movimiento" :subtitle="'Inventario de '.$product->name" />

    <div class="row g-4">
        <div class="col-12 col-xl-4">
            <section class="card shadow-sm" aria-labelledby="current-balances-heading">
                <div class="card-header">
                    <h2 class="h5 mb-0" id="current-balances-heading">Saldos actuales</h2>
                </div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt>Producto</dt>
                        <dd>{{ $product->name }}</dd>

                        <dt>SKU</dt>
                        <dd><code>{{ $product->sku }}</code></dd>

                        <dt>Stock total</dt>
                        <dd>{{ $inventory->stock }}</dd>

                        <dt>Reservado</dt>
                        <dd>{{ $inventory->reserved_stock }}</dd>

                        <dt>Disponible</dt>
                        <dd>{{ $inventory->available_stock }}</dd>
                    </dl>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.inventory.movements.store', $inventory) }}">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">

                        <div class="mb-3">
                            <label class="form-label" for="type">Tipo de movimiento</label>
                            <select
                                class="form-select @error('type') is-invalid @enderror"
                                id="type"
                                name="type"
                                required
                                aria-describedby="type-help @error('type') type-error @enderror"
                            >
                                @foreach ($typeOptions as $type => $option)
                                    <option value="{{ $type }}" @selected(old('type', $selectedType) === $type)>
                                        {{ $option['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text" id="type-help">Selecciona la operación que corresponde al movimiento manual.</div>
                            @error('type')
                                <div class="invalid-feedback" id="type-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="alert alert-info" role="note" aria-labelledby="operation-effects-heading">
                            <h2 class="h6" id="operation-effects-heading">Efecto de cada operación</h2>
                            <ul class="mb-0">
                                @foreach ($typeOptions as $option)
                                    <li><strong>{{ $option['label'] }}:</strong> {{ $option['description'] }}</li>
                                @endforeach
                            </ul>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="quantity">Cantidad</label>
                                <input
                                    class="form-control @error('quantity') is-invalid @enderror"
                                    id="quantity"
                                    name="quantity"
                                    type="number"
                                    value="{{ old('quantity') }}"
                                    min="1"
                                    step="1"
                                    inputmode="numeric"
                                    aria-describedby="quantity-help @error('quantity') quantity-error @enderror"
                                >
                                <div class="form-text" id="quantity-help">Obligatoria para entradas, salidas, reservas y liberaciones.</div>
                                @error('quantity')
                                    <div class="invalid-feedback" id="quantity-error">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label" for="new_stock">Nuevo stock total</label>
                                <input
                                    class="form-control @error('new_stock') is-invalid @enderror"
                                    id="new_stock"
                                    name="new_stock"
                                    type="number"
                                    value="{{ old('new_stock') }}"
                                    min="0"
                                    step="1"
                                    inputmode="numeric"
                                    aria-describedby="new-stock-help @error('new_stock') new-stock-error @enderror"
                                >
                                <div class="form-text" id="new-stock-help">Obligatorio únicamente para ajustes.</div>
                                @error('new_stock')
                                    <div class="invalid-feedback" id="new-stock-error">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="reason">Motivo</label>
                            <textarea
                                class="form-control @error('reason') is-invalid @enderror"
                                id="reason"
                                name="reason"
                                rows="4"
                                maxlength="255"
                                required
                                aria-describedby="reason-help @error('reason') reason-error @enderror"
                            >{{ old('reason') }}</textarea>
                            <div class="form-text" id="reason-help">Describe por qué se realiza esta operación manual.</div>
                            @error('reason')
                                <div class="invalid-feedback" id="reason-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-primary" type="submit">Confirmar movimiento</button>
                            <a class="btn btn-outline-secondary" href="{{ route('admin.inventory.show', $inventory) }}">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
