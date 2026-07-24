@extends('layouts.public')

@section('title', 'Pago con tarjeta | Almacenes al Costo')
@section('description', 'Pago seguro con tarjeta mediante Datafast Dataweb.')

@section('content')
    <section class="public-section-placeholder py-5" aria-labelledby="datafast-widget-title">
        <div class="container">
            <header class="mb-4">
                <p class="section-eyebrow mb-2">Pago con tarjeta</p>
                <h1 class="display-6 fw-bold mb-3" id="datafast-widget-title">Tarjeta de debito o credito</h1>
                <p class="lead text-body-secondary mb-0">
                    Pedido: <strong>{{ $widget['orderReference'] }}</strong>
                    - Total: <strong>$ {{ App\Support\Money::format($order->total) }}</strong>
                </p>
            </header>

            <div class="section-placeholder-card p-4">
                <div class="alert alert-info mb-4">
                    Los datos de tu tarjeta seran procesados de forma segura por Datafast.
                    Almacenes al Costo no almacena el numero, fecha de vencimiento ni CVV de tu tarjeta.
                </div>

                <form
                    action="{{ route('orders.payment.datafast.result', [
                        'orderReference' => $widget['orderReference'],
                        'paymentReference' => $widget['paymentReference'],
                    ]) }}"
                    class="paymentWidgets"
                    data-brands="{{ $widget['brands'] }}"
                ></form>
            </div>
        </div>
    </section>

    <script src="{{ $widget['widgetScriptUrl'] }}"></script>
@endsection
