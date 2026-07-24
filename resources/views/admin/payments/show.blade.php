@extends('layouts.admin')

@section('title', 'Detalle de Pago | Admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Revisión de comprobante</h1>
            <p class="text-body-secondary mb-0">Pedido: <code>{{ $order->reference }}</code></p>
        </div>
        <a href="{{ route('admin.payments.index') }}" class="btn btn-outline-secondary btn-sm">← Volver</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row g-4">
        {{-- Order summary --}}
        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Información del pedido</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">Estado</dt>
                        <dd class="col-7">
                            <span class="badge text-bg-warning">{{ $order->status }}</span>
                        </dd>
                        <dt class="col-5">Cliente</dt>
                        <dd class="col-7">{{ $order->customer_name }}</dd>
                        <dt class="col-5">Email</dt>
                        <dd class="col-7">{{ $order->customer_email }}</dd>
                        <dt class="col-5">Teléfono</dt>
                        <dd class="col-7">{{ $order->customer_phone }}</dd>
                        <dt class="col-5">Total</dt>
                        <dd class="col-7 fw-bold">$ {{ App\Support\Money::format($order->total) }}</dd>
                    </dl>
                </div>
            </div>

            {{-- Items --}}
            <div class="card mb-4">
                <div class="card-header fw-semibold">Ítems del pedido</div>
                <ul class="list-group list-group-flush">
                    @foreach ($order->items as $item)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $item->product_name }} × {{ $item->quantity }}</span>
                            <span>$ {{ App\Support\Money::format($item->subtotal) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Receipt and actions --}}
        <div class="col-lg-7">
            {{-- Receipt info --}}
            @if ($order->receipt)
                <div class="card mb-4">
                    <div class="card-header fw-semibold">Comprobante adjunto</div>
                    <div class="card-body">
                        <dl class="row mb-3">
                            <dt class="col-5">Método</dt>
                            <dd class="col-7">{{ $order->receipt->payment_method }}</dd>
                            @if ($order->receipt->transaction_reference)
                                <dt class="col-5">N° comprobante</dt>
                                <dd class="col-7">{{ $order->receipt->transaction_reference }}</dd>
                            @endif
                            <dt class="col-5">Enviado el</dt>
                            <dd class="col-7">{{ $order->receipt->uploaded_at?->format('d/m/Y H:i') }}</dd>
                            @if ($order->receipt->rejection_reason)
                                <dt class="col-5">Motivo anterior</dt>
                                <dd class="col-7 text-danger">{{ $order->receipt->rejection_reason }}</dd>
                            @endif
                        </dl>

                        <a
                            href="{{ route('admin.payments.receipt.download', $order->id) }}"
                            class="btn btn-outline-secondary btn-sm"
                            id="download-receipt-btn"
                            target="_blank"
                        >
                            📄 Descargar comprobante
                        </a>
                    </div>
                </div>
            @else
                <div class="alert alert-warning mb-4">No se encontró comprobante adjunto para este pedido.</div>
            @endif

            {{-- Review actions --}}
            @if ($order->isValidating())
                <div class="card">
                    <div class="card-header fw-semibold">Acción de revisión</div>
                    <div class="card-body">
                        <form
                            method="POST"
                            action="{{ route('admin.payments.review', $order->id) }}"
                            id="payment-review-form"
                        >
                            @csrf

                            <div class="mb-3" id="rejection-reason-group" style="display: none;">
                                <label for="reason" class="form-label">Motivo del rechazo <span class="text-danger">*</span></label>
                                <textarea
                                    class="form-control @error('reason') is-invalid @enderror"
                                    id="reason"
                                    name="reason"
                                    rows="3"
                                    maxlength="1000"
                                >{{ old('reason') }}</textarea>
                                @error('reason')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-flex gap-2">
                                <button
                                    type="submit"
                                    name="action"
                                    value="approve"
                                    class="btn btn-success"
                                    id="approve-payment-btn"
                                    onclick="return confirm('¿Confirma la aprobación de este pago?')"
                                >
                                    ✓ Aprobar pago
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-danger"
                                    id="show-reject-form-btn"
                                    onclick="
                                        document.getElementById('rejection-reason-group').style.display='block';
                                        document.getElementById('show-reject-form-btn').style.display='none';
                                        document.getElementById('reject-payment-btn').style.display='inline-block';
                                    "
                                >
                                    ✗ Rechazar
                                </button>
                                <button
                                    type="submit"
                                    name="action"
                                    value="reject"
                                    class="btn btn-danger"
                                    id="reject-payment-btn"
                                    style="display: none;"
                                >
                                    Confirmar rechazo
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @else
                <div class="alert alert-secondary">
                    Este pedido ya fue procesado (estado: {{ $order->status }}).
                </div>
            @endif

            {{-- Payment audit trail --}}
            @if ($pendingPayment && $pendingPayment->transactions->isNotEmpty())
                <div class="card mt-4">
                    <div class="card-header fw-semibold">Historial de transacciones</div>
                    <ul class="list-group list-group-flush">
                        @foreach ($pendingPayment->transactions->sortByDesc('id') as $tx)
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <span class="badge text-bg-secondary">{{ $tx->event_type }}</span>
                                    <small class="text-body-secondary">{{ $tx->created_at?->format('d/m/Y H:i:s') }}</small>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
@endsection
