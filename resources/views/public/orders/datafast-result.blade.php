@extends('layouts.public')

@section('title', 'Resultado del pago | Almacenes al Costo')
@section('description', 'Resultado seguro del pago con tarjeta.')

@section('content')
    <section class="public-section-placeholder py-5" aria-labelledby="datafast-result-title">
        <div class="container">
            <header class="mb-4">
                <p class="section-eyebrow mb-2">Pago</p>
                <h1 class="display-6 fw-bold mb-3" id="datafast-result-title">Resultado del pago</h1>
            </header>

            @if ($paymentResponse->status === 'completed')
                <div class="alert alert-success mb-4">
                    <h2 class="h5 mb-2">Pago confirmado</h2>
                    <p class="mb-0">Tu pago con tarjeta fue verificado correctamente. Pedido: <strong>{{ $order->reference }}</strong></p>
                </div>
                <a href="{{ route('orders.confirmation', ['orderReference' => $order->reference]) }}" class="btn btn-brand">
                    Ver confirmacion
                </a>
            @elseif ($paymentResponse->status === 'failed')
                <div class="alert alert-danger mb-4">
                    <h2 class="h5 mb-2">Pago no completado</h2>
                    <p class="mb-0">{{ $paymentResponse->message ?? 'Datafast no aprobo el pago.' }}</p>
                </div>
                <a href="{{ route('orders.payment.show', ['orderReference' => $order->reference]) }}" class="btn btn-brand">
                    Intentar nuevamente
                </a>
            @else
                <div class="alert alert-warning mb-4">
                    <h2 class="h5 mb-2">Pago en verificacion</h2>
                    <p class="mb-0">
                        {{ $paymentResponse->message ?? 'Estamos verificando el pago con Datafast.' }}
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
