@extends('layouts.admin')

@section('title', 'Revisión de Pagos | Admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Comprobantes pendientes de revisión</h1>
        <span class="badge bg-warning text-dark">{{ $orders->total() }} pendiente(s)</span>
    </div>

    @if ($orders->isEmpty())
        <div class="alert alert-info">No hay comprobantes pendientes de revisión en este momento.</div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Referencia</th>
                        <th scope="col">Cliente</th>
                        <th scope="col">Total</th>
                        <th scope="col">Fecha</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr>
                            <td>
                                <code class="small">{{ $order->reference }}</code>
                            </td>
                            <td>
                                <div>{{ $order->customer_name }}</div>
                                <small class="text-body-secondary">{{ $order->customer_email }}</small>
                            </td>
                            <td class="fw-semibold">$ {{ App\Support\Money::format($order->total) }}</td>
                            <td>
                                <small>{{ $order->created_at?->format('d/m/Y H:i') }}</small>
                            </td>
                            <td>
                                <span class="badge text-bg-warning">Validando</span>
                            </td>
                            <td class="text-end">
                                <a
                                    href="{{ route('admin.payments.show', $order->id) }}"
                                    class="btn btn-sm btn-outline-primary"
                                    id="review-order-{{ $order->id }}"
                                >
                                    Revisar
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $orders->links() }}
        </div>
    @endif
@endsection
