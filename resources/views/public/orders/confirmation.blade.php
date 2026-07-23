@extends('layouts.public')

@section('title', 'Confirmación de pedido | Almacenes al Costo')
@section('description', 'Tu pedido fue registrado correctamente. Continúa con el proceso de pago.')

@section('content')
    @php
        $deliveryLabel = $order->isHomeDelivery() ? 'Entrega a domicilio' : 'Retiro en tienda';
        $shippingLabel = $order->isHomeDelivery() ? 'Costo por confirmar' : '$ '.App\Support\Money::format($order->shipping_cost);
    @endphp

    <section class="public-section-placeholder py-5" aria-labelledby="order-confirmation-title">
        <div class="container">
            <header class="mb-4">
                <p class="section-eyebrow mb-2">Compra</p>
                <h1 class="display-6 fw-bold mb-3" id="order-confirmation-title">Pedido registrado</h1>
                <p class="lead text-body-secondary mb-0">
                    Tu pedido fue creado correctamente. El pago se gestionará en el siguiente paso del proceso.
                </p>
            </header>

            <div class="section-placeholder-card p-4 mb-4">
                <h2 class="h5 mb-3">Referencia del pedido</h2>
                <p class="mb-2"><strong>{{ $order->reference }}</strong></p>
                <p class="mb-0">
                    Estado inicial:
                    <span class="badge text-bg-warning">Pendiente de pago</span>
                </p>
            </div>

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="section-placeholder-card p-4 mb-4">
                        <h2 class="h5 mb-3">Resumen del pedido</h2>
                        <ul class="list-unstyled mb-0">
                            @foreach ($order->items as $item)
                                <li class="d-flex justify-content-between gap-3 mb-3">
                                    <div>
                                        <strong>{{ $item->product_name }}</strong>
                                        <div class="text-body-secondary small">{{ $item->quantity }} × $ {{ App\Support\Money::format($item->unit_price) }}</div>
                                    </div>
                                    <span>$ {{ App\Support\Money::format($item->subtotal) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="section-placeholder-card p-4">
                        <h2 class="h5 mb-3">Entrega</h2>
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Método</dt>
                            <dd class="col-sm-8">{{ $deliveryLabel }}</dd>
                            <dt class="col-sm-4">Cliente</dt>
                            <dd class="col-sm-8">{{ $order->customer_name }}</dd>
                            <dt class="col-sm-4">Correo</dt>
                            <dd class="col-sm-8">{{ $order->customer_email }}</dd>
                            <dt class="col-sm-4">Teléfono</dt>
                            <dd class="col-sm-8">{{ $order->customer_phone }}</dd>
                            @if ($order->isHomeDelivery())
                                <dt class="col-sm-4">Provincia</dt>
                                <dd class="col-sm-8">{{ $order->province }}</dd>
                                <dt class="col-sm-4">Ciudad</dt>
                                <dd class="col-sm-8">{{ $order->city }}</dd>
                                <dt class="col-sm-4">Dirección</dt>
                                <dd class="col-sm-8">{{ $order->address }}</dd>
                            @endif
                        </dl>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="section-placeholder-card p-4">
                        <h2 class="h5 mb-3">Totales</h2>
                        <dl class="row mb-4">
                            <dt class="col-6">Subtotal</dt>
                            <dd class="col-6 text-end">$ {{ App\Support\Money::format($order->subtotal) }}</dd>
                            <dt class="col-6">Envío</dt>
                            <dd class="col-6 text-end">{{ $shippingLabel }}</dd>
                            <dt class="col-6 fw-bold">Total</dt>
                            <dd class="col-6 text-end fw-bold">$ {{ App\Support\Money::format($order->total) }}</dd>
                        </dl>

                        <p class="text-body-secondary mb-4">
                            Guarda tu referencia de pedido. Te indicaremos cómo completar el pago en una etapa posterior.
                        </p>

                        <a class="btn btn-brand" href="{{ route('catalog.index') }}">Volver al catálogo</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
