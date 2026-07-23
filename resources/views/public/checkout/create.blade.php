@extends('layouts.public')

@section('title', 'Checkout | Almacenes al Costo')
@section('description', 'Completa tus datos de contacto y entrega para continuar con tu pedido.')

@section('content')
    <section class="public-section-placeholder py-5" aria-labelledby="checkout-title">
        <div class="container">
            <header class="mb-4">
                <p class="section-eyebrow mb-2">Compra</p>
                <h1 class="display-6 fw-bold mb-3" id="checkout-title">Checkout</h1>
                <p class="lead text-body-secondary mb-0">Ingresa tus datos para revisar el pedido antes de confirmarlo.</p>
            </header>

            <div class="row g-4">
                <div class="col-lg-7">
                    <form class="section-placeholder-card p-4" method="POST" action="{{ route('checkout.review') }}" novalidate>
                        @csrf

                        <h2 class="h5 mb-3">Datos del cliente</h2>
                        <div class="mb-3">
                            <label class="form-label" for="customer_name">Nombre completo</label>
                            <input class="form-control @error('customer_name') is-invalid @enderror" id="customer_name" name="customer_name" type="text" value="{{ old('customer_name') }}" required>
                            @error('customer_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="customer_email">Correo electrónico</label>
                            <input class="form-control @error('customer_email') is-invalid @enderror" id="customer_email" name="customer_email" type="email" value="{{ old('customer_email') }}" required>
                            @error('customer_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="customer_phone">Teléfono</label>
                            <input class="form-control @error('customer_phone') is-invalid @enderror" id="customer_phone" name="customer_phone" type="tel" value="{{ old('customer_phone') }}" required>
                            @error('customer_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <h2 class="h5 mb-3">Entrega</h2>
                        <fieldset class="mb-3">
                            <legend class="form-label">Método de entrega</legend>
                            <div class="form-check">
                                <input class="form-check-input" id="delivery_store_pickup" name="delivery_method" type="radio" value="store_pickup" @checked(old('delivery_method', 'store_pickup') === 'store_pickup')>
                                <label class="form-check-label" for="delivery_store_pickup">Retiro en tienda</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" id="delivery_home" name="delivery_method" type="radio" value="home_delivery" @checked(old('delivery_method') === 'home_delivery')>
                                <label class="form-check-label" for="delivery_home">Entrega a domicilio</label>
                            </div>
                            @error('delivery_method')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </fieldset>

                        <div class="mb-3">
                            <label class="form-label" for="province">Provincia</label>
                            <input class="form-control @error('province') is-invalid @enderror" id="province" name="province" type="text" value="{{ old('province') }}">
                            @error('province')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="city">Ciudad</label>
                            <input class="form-control @error('city') is-invalid @enderror" id="city" name="city" type="text" value="{{ old('city') }}">
                            @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="address">Dirección</label>
                            <input class="form-control @error('address') is-invalid @enderror" id="address" name="address" type="text" value="{{ old('address') }}">
                            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="delivery_reference">Referencia de ubicación (opcional)</label>
                            <input class="form-control @error('delivery_reference') is-invalid @enderror" id="delivery_reference" name="delivery_reference" type="text" value="{{ old('delivery_reference') }}">
                            @error('delivery_reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="notes">Notas (opcional)</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-outline-brand" href="{{ route('cart.index') }}">Volver al carrito</a>
                            <button class="btn btn-brand" type="submit">Revisar pedido</button>
                        </div>
                    </form>
                </div>

                <div class="col-lg-5">
                    <div class="section-placeholder-card p-4">
                        <h2 class="h5 mb-3">Resumen del carrito</h2>
                        <ul class="list-unstyled mb-4">
                            @foreach ($cart['items'] as $item)
                                <li class="d-flex justify-content-between gap-3 mb-2">
                                    <span>{{ $item['product']->name }} × {{ $item['quantity'] }}</span>
                                    <span>$ {{ App\Support\Money::format($item['line_subtotal']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <dl class="row mb-0">
                            <dt class="col-6">Subtotal</dt>
                            <dd class="col-6 text-end">$ {{ App\Support\Money::format($cart['subtotal']) }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
