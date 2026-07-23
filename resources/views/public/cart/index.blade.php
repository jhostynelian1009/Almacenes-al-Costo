@extends('layouts.public')

@section('title', 'Carrito | Almacenes al Costo')
@section('description', 'Revisa los productos seleccionados en tu carrito de compras.')

@section('content')
    <section class="public-section-placeholder py-5" aria-labelledby="cart-title">
        <div class="container">
            <header class="mb-4">
                <p class="section-eyebrow mb-2">Compra</p>
                <h1 class="display-6 fw-bold mb-3" id="cart-title">Carrito</h1>
                <p class="lead text-body-secondary mb-0">Revisa tus productos antes de continuar al checkout.</p>
            </header>

            @if ($cart['items'] === [])
                <x-public.empty-state
                    class="section-placeholder-card"
                    name="cart-empty"
                    title="Tu carrito está vacío"
                    message="Explora el catálogo y agrega productos disponibles."
                    :heading-level="2"
                />
                <div class="mt-4">
                    <a class="btn btn-brand" href="{{ route('catalog.index') }}">Ir al catálogo</a>
                </div>
            @else
                <div class="table-responsive section-placeholder-card mb-4">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Producto</th>
                                <th scope="col">Precio unitario</th>
                                <th scope="col">Cantidad</th>
                                <th scope="col">Subtotal</th>
                                <th scope="col"><span class="visually-hidden">Acciones</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cart['items'] as $item)
                                <tr>
                                    <td>
                                        <a class="link-brand fw-semibold" href="{{ route('catalog.show', $item['product']->slug) }}">
                                            {{ $item['product']->name }}
                                        </a>
                                    </td>
                                    <td>$ {{ App\Support\Money::format($item['unit_price']) }}</td>
                                    <td>
                                        <form class="d-flex align-items-center gap-2" method="POST" action="{{ route('cart.items.update', $item['product']->slug) }}">
                                            @csrf
                                            @method('PATCH')
                                            <label class="visually-hidden" for="quantity-{{ $item['product']->slug }}">Cantidad de {{ $item['product']->name }}</label>
                                            <input
                                                class="form-control form-control-sm"
                                                id="quantity-{{ $item['product']->slug }}"
                                                name="quantity"
                                                type="number"
                                                min="1"
                                                max="{{ $item['available_stock'] }}"
                                                value="{{ $item['quantity'] }}"
                                                required
                                            >
                                            <button class="btn btn-sm btn-outline-brand" type="submit">Actualizar</button>
                                        </form>
                                    </td>
                                    <td>$ {{ App\Support\Money::format($item['line_subtotal']) }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('cart.items.destroy', $item['product']->slug) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="row g-4 align-items-start">
                    <div class="col-lg-6">
                        <form method="POST" action="{{ route('cart.clear') }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-outline-secondary" type="submit">Vaciar carrito</button>
                        </form>
                    </div>
                    <div class="col-lg-6">
                        <div class="section-placeholder-card p-4">
                            <h2 class="h5 mb-3">Resumen</h2>
                            <dl class="row mb-4">
                                <dt class="col-6">Unidades</dt>
                                <dd class="col-6 text-end">{{ $cart['total_units'] }}</dd>
                                <dt class="col-6">Subtotal</dt>
                                <dd class="col-6 text-end">$ {{ App\Support\Money::format($cart['subtotal']) }}</dd>
                            </dl>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="btn btn-outline-brand" href="{{ route('catalog.index') }}">Seguir comprando</a>
                                <a class="btn btn-brand" href="{{ route('checkout.create') }}">Ir al checkout</a>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection
