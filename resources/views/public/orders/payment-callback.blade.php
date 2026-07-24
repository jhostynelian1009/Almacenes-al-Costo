@extends('layouts.public')

@section('title', 'Estado del pago | Almacenes al Costo')
@section('description', 'Estado de tu pago y pedido.')

@section('content')
    <section class="public-section-placeholder py-5" aria-labelledby="payment-callback-title">
        <div class="container">
            <header class="mb-4">
                <p class="section-eyebrow mb-2">Pago</p>
                <h1 class="display-6 fw-bold mb-3" id="payment-callback-title">Estado del pago</h1>
            </header>

            @if ($order === null)
                <div class="section-placeholder-card p-4">
                    <p class="mb-3 text-body-secondary">No se pudo encontrar la información de tu pedido.</p>
                    <a href="{{ route('catalog.index') }}" class="btn btn-brand">Volver al catálogo</a>
                </div>
            @elseif ($status === 'paid' || $status === 'approved')
                <div class="alert alert-success mb-4">
                    <h2 class="h5 mb-2">¡Pago confirmado!</h2>
                    <p class="mb-0">Tu pago fue verificado exitosamente. Pedido: <strong>{{ $order->reference }}</strong></p>
                </div>
                <a href="{{ route('orders.confirmation', ['orderReference' => $order->reference]) }}" class="btn btn-brand">
                    Ver confirmación
                </a>
            @elseif ($status === 'canceled')
                <div class="alert alert-danger mb-4">
                    <h2 class="h5 mb-2">Pago no completado</h2>
                    <p class="mb-0">Tu pago no pudo ser procesado. Puedes intentarlo nuevamente.</p>
                </div>
                <a href="{{ route('orders.payment.show', ['orderReference' => $order->reference]) }}" class="btn btn-brand">
                    Intentar nuevamente
                </a>
            @else
                <div class="alert alert-warning mb-4">
                    <h2 class="h5 mb-2">Pago en proceso</h2>
                    <p class="mb-0">
                        Tu pago está siendo verificado. Recibirás una confirmación cuando sea procesado.
                        Pedido: <strong>{{ $order->reference }}</strong>
                    </p>
                </div>
                <a href="{{ route('orders.confirmation', ['orderReference' => $order->reference]) }}" class="btn btn-brand">
                    Ver estado del pedido
                </a>
            @endif
        </div>
    </section>
@endsection
