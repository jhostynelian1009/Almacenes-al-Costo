@extends('layouts.public')

@section('title', 'Pago del pedido | Almacenes al Costo')
@section('description', 'Completa el pago de tu pedido mediante transferencia bancaria, Deuna o tarjeta.')

@section('content')
    <section class="public-section-placeholder py-5" aria-labelledby="payment-title">
        <div class="container">
            <header class="mb-4">
                <p class="section-eyebrow mb-2">Pago</p>
                <h1 class="display-6 fw-bold mb-3" id="payment-title">Completar pago</h1>
                <p class="lead text-body-secondary mb-0">
                    Pedido: <strong>{{ $order->reference }}</strong> — Total: <strong>$ {{ App\Support\Money::format($order->total) }}</strong>
                </p>
            </header>

            @if ($errors->any())
                <div class="alert alert-danger mb-4">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger mb-4">{{ session('error') }}</div>
            @endif

            @php
                $datafastConfigured = in_array('datafast', $enabledMethods, true) && ($datafastReadiness['enabled'] ?? false);
                $datafastReady = $datafastConfigured && ($datafastReadiness['ready'] ?? false);
            @endphp

            <div class="row g-4">
                {{-- Método: Transferencia Bancaria --}}
                @if (in_array('transfer', $enabledMethods))
                    <div class="col-lg-6">
                        <div class="section-placeholder-card p-4 h-100">
                            <h2 class="h5 mb-3">
                                <span class="me-2">🏦</span> Transferencia bancaria
                            </h2>
                            <p class="text-body-secondary mb-3">
                                Realiza una transferencia a la siguiente cuenta y adjunta el comprobante.
                            </p>

                            <dl class="row mb-4">
                                @if (!empty($bankConfig['name']))
                                    <dt class="col-sm-5">Banco</dt>
                                    <dd class="col-sm-7">{{ $bankConfig['name'] }}</dd>
                                @endif
                                @if (!empty($bankConfig['account_holder']))
                                    <dt class="col-sm-5">Titular</dt>
                                    <dd class="col-sm-7">{{ $bankConfig['account_holder'] }}</dd>
                                @endif
                                @if (!empty($bankConfig['account_number']))
                                    <dt class="col-sm-5">Cuenta</dt>
                                    <dd class="col-sm-7">{{ $bankConfig['account_number'] }}</dd>
                                @endif
                                @if (!empty($bankConfig['account_type']))
                                    <dt class="col-sm-5">Tipo</dt>
                                    <dd class="col-sm-7">{{ $bankConfig['account_type'] }}</dd>
                                @endif
                                @if (!empty($bankConfig['identification']))
                                    <dt class="col-sm-5">RUC/Cédula</dt>
                                    <dd class="col-sm-7">{{ $bankConfig['identification'] }}</dd>
                                @endif
                                <dt class="col-sm-5">Monto exacto</dt>
                                <dd class="col-sm-7 fw-bold">$ {{ App\Support\Money::format($order->total) }}</dd>
                                @if (!empty($bankConfig['reference_instructions']))
                                    <dt class="col-sm-5">Referencia</dt>
                                    <dd class="col-sm-7">{{ $bankConfig['reference_instructions'] }}</dd>
                                @endif
                            </dl>

                            <form
                                id="upload-receipt-form"
                                method="POST"
                                action="{{ route('orders.payment.upload', ['orderReference' => $order->reference]) }}"
                                enctype="multipart/form-data"
                            >
                                @csrf
                                <input type="hidden" name="payment_method" value="transfer">

                                <div class="mb-3">
                                    <label for="receipt" class="form-label">Comprobante de transferencia</label>
                                    <input
                                        type="file"
                                        class="form-control @error('receipt') is-invalid @enderror"
                                        id="receipt"
                                        name="receipt"
                                        accept=".pdf,.jpg,.jpeg,.png,.webp"
                                        required
                                    >
                                    @error('receipt')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">PDF, JPEG, PNG o WebP. Máximo 4 MB.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="transaction_reference" class="form-label">
                                        Número de comprobante <span class="text-body-secondary">(opcional)</span>
                                    </label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="transaction_reference"
                                        name="transaction_reference"
                                        maxlength="100"
                                        value="{{ old('transaction_reference') }}"
                                    >
                                </div>

                                <button type="submit" class="btn btn-brand w-100" id="submit-receipt-btn">
                                    Enviar comprobante
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                {{-- Método: Deuna --}}
                @if (in_array('deuna', $enabledMethods))
                    <div class="col-lg-6">
                        <div class="section-placeholder-card p-4 h-100">
                            <h2 class="h5 mb-3">
                                <span class="me-2">📱</span> Pago con Deuna
                            </h2>
                            <p class="text-body-secondary mb-3">
                                Escanea el código QR con la aplicación Deuna para realizar tu pago.
                            </p>

                            @php $deunaQr = config('payment.deuna.qr_image_url', ''); @endphp
                            @if ($deunaQr)
                                <div class="text-center mb-4">
                                    <img src="{{ $deunaQr }}" alt="Código QR Deuna" class="img-fluid" style="max-width: 200px;">
                                </div>
                            @else
                                <div class="alert alert-info mb-4">
                                    El código QR de Deuna será configurado pronto. Por favor usa la opción de transferencia bancaria.
                                </div>
                            @endif

                            <p class="small text-body-secondary">
                                Una vez realizado el pago, adjunta el comprobante generado por Deuna.
                            </p>

                            <form
                                method="POST"
                                action="{{ route('orders.payment.upload', ['orderReference' => $order->reference]) }}"
                                enctype="multipart/form-data"
                            >
                                @csrf
                                <input type="hidden" name="payment_method" value="deuna">

                                <div class="mb-3">
                                    <label for="receipt-deuna" class="form-label">Comprobante Deuna</label>
                                    <input
                                        type="file"
                                        class="form-control"
                                        id="receipt-deuna"
                                        name="receipt"
                                        accept=".pdf,.jpg,.jpeg,.png,.webp"
                                        required
                                    >
                                    <div class="form-text">PDF, JPEG, PNG o WebP. Máximo 4 MB.</div>
                                </div>

                                <button type="submit" class="btn btn-outline-brand w-100">
                                    Enviar comprobante Deuna
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                {{-- Metodo: Datafast Dataweb --}}
                @if ($datafastReady)
                    <div class="col-lg-6">
                        <div class="section-placeholder-card p-4 h-100">
                            <h2 class="h5 mb-3">Tarjeta de debito o credito</h2>
                            <p class="text-body-secondary mb-3">
                                Paga con tarjeta mediante el formulario seguro de Datafast Dataweb.
                            </p>
                            <p class="small text-body-secondary">
                                Los datos de tu tarjeta seran procesados de forma segura por Datafast.
                                Almacenes al Costo no almacena el numero, fecha de vencimiento ni CVV de tu tarjeta.
                            </p>

                            <form
                                method="POST"
                                action="{{ route('orders.payment.process', ['orderReference' => $order->reference]) }}"
                            >
                                @csrf
                                <input type="hidden" name="payment_method" value="datafast">

                                <button type="submit" class="btn btn-brand w-100">
                                    Pagar con tarjeta
                                </button>
                            </form>
                        </div>
                    </div>
                @elseif ($datafastConfigured)
                    <div class="col-lg-6">
                        <div class="section-placeholder-card p-4 h-100">
                            <h2 class="h5 mb-3">Tarjeta de debito o credito</h2>
                            <div class="alert alert-info mb-0">
                                {{ $datafastReadiness['message'] ?? 'Pago con tarjeta no disponible para este pedido.' }}
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
