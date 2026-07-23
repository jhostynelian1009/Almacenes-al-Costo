@extends('layouts.public')

@section('title', 'Revisar pedido | Almacenes al Costo')
@section('description', 'Confirma los datos de tu pedido antes de registrarlo.')

@section('content')
    @php
        $checkoutData = $review['checkout_data'];
        $deliveryLabel = $checkoutData['delivery_method'] === 'home_delivery' ? 'Entrega a domicilio' : 'Retiro en tienda';
    @endphp

    <section class="public-section-placeholder py-5" aria-labelledby="checkout-review-title">
        <div class="container">
            <header class="mb-4">
                <p class="section-eyebrow mb-2">Compra</p>
                <h1 class="display-6 fw-bold mb-3" id="checkout-review-title">Revisar pedido</h1>
                <p class="lead text-body-secondary mb-0">Verifica la información antes de confirmar tu pedido.</p>
            </header>

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="section-placeholder-card p-4 mb-4">
                        <h2 class="h5 mb-3">Productos</h2>
                        <ul class="list-unstyled mb-0">
                            @foreach ($review['items'] as $item)
                                <li class="d-flex justify-content-between gap-3 mb-3">
                                    <div>
                                        <strong>{{ $item['product']->name }}</strong>
                                        <div class="text-body-secondary small">{{ $item['quantity'] }} × $ {{ App\Support\Money::format($item['unit_price']) }}</div>
                                    </div>
                                    <span>$ {{ App\Support\Money::format($item['line_subtotal']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="section-placeholder-card p-4">
                        <h2 class="h5 mb-3">Datos de contacto y entrega</h2>
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Nombre</dt>
                            <dd class="col-sm-8">{{ $checkoutData['customer_name'] }}</dd>
                            <dt class="col-sm-4">Correo</dt>
                            <dd class="col-sm-8">{{ $checkoutData['customer_email'] }}</dd>
                            <dt class="col-sm-4">Teléfono</dt>
                            <dd class="col-sm-8">{{ $checkoutData['customer_phone'] }}</dd>
                            <dt class="col-sm-4">Entrega</dt>
                            <dd class="col-sm-8">{{ $deliveryLabel }}</dd>
                            @if ($checkoutData['delivery_method'] === 'home_delivery')
                                <dt class="col-sm-4">Provincia</dt>
                                <dd class="col-sm-8">{{ $checkoutData['province'] }}</dd>
                                <dt class="col-sm-4">Ciudad</dt>
                                <dd class="col-sm-8">{{ $checkoutData['city'] }}</dd>
                                <dt class="col-sm-4">Dirección</dt>
                                <dd class="col-sm-8">{{ $checkoutData['address'] }}</dd>
                            @endif
                            @if (! empty($checkoutData['delivery_reference']))
                                <dt class="col-sm-4">Referencia</dt>
                                <dd class="col-sm-8">{{ $checkoutData['delivery_reference'] }}</dd>
                            @endif
                            @if (! empty($checkoutData['notes']))
                                <dt class="col-sm-4">Notas</dt>
                                <dd class="col-sm-8">{{ $checkoutData['notes'] }}</dd>
                            @endif
                        </dl>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="section-placeholder-card p-4">
                        <h2 class="h5 mb-3">Totales</h2>
                        <dl class="row mb-4">
                            <dt class="col-6">Subtotal</dt>
                            <dd class="col-6 text-end">$ {{ App\Support\Money::format($review['subtotal']) }}</dd>
                            <dt class="col-6">Envío</dt>
                            <dd class="col-6 text-end">{{ $review['shipping_label'] }}</dd>
                            <dt class="col-6 fw-bold">Total</dt>
                            <dd class="col-6 text-end fw-bold">$ {{ App\Support\Money::format($review['total']) }}</dd>
                        </dl>

                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-outline-brand" href="{{ route('checkout.create') }}">Editar datos</a>
                            <form method="POST" action="{{ route('checkout.store') }}">
                                @csrf
                                <button class="btn btn-brand" type="submit">Confirmar pedido</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
