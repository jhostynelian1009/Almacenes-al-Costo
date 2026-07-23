@php
    $navigation = [
        ['label' => 'Inicio', 'route' => 'home', 'active' => 'home'],
        ['label' => 'Catálogo', 'route' => 'catalog.index', 'active' => 'catalog.*'],
        ['label' => 'Categorías', 'route' => 'categories.index', 'active' => 'categories.*'],
        ['label' => 'Promociones', 'route' => 'promotions.index', 'active' => 'promotions.*'],
        ['label' => 'Información', 'route' => 'information', 'active' => 'information'],
    ];
@endphp

<nav class="navbar navbar-expand-lg public-navbar sticky-top" aria-label="Navegación principal">
    <div class="container">
        <a class="navbar-brand brand-mark" href="{{ route('home') }}" aria-label="Almacenes al Costo, ir al inicio">
            <span class="brand-mark__symbol" aria-hidden="true">AC</span>
            <span>Almacenes al Costo</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavigation" aria-controls="publicNavigation" aria-expanded="false" aria-label="Abrir menú de navegación">
            <span class="navbar-toggler-icon" aria-hidden="true"></span>
        </button>

        <div class="collapse navbar-collapse" id="publicNavigation">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                @foreach ($navigation as $item)
                    @php($isActive = request()->routeIs($item['active']))
                    <li class="nav-item">
                        <a class="nav-link {{ $isActive ? 'active' : '' }}" href="{{ route($item['route']) }}" @if ($isActive) aria-current="page" @endif>
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
                <li class="nav-item ms-lg-2">
                    <a class="btn btn-outline-brand position-relative" href="{{ route('cart.index') }}">
                        Carrito
                        @if (($cartUnitCount ?? 0) > 0)
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-brand">
                                {{ $cartUnitCount }}
                                <span class="visually-hidden">productos en el carrito</span>
                            </span>
                        @endif
                    </a>
                </li>
                <li class="nav-item ms-lg-2">
                    <a class="btn btn-outline-brand" href="{{ auth()->check() ? route('admin.dashboard') : route('login') }}">
                        {{ auth()->check() ? 'Panel interno' : 'Acceso interno' }}
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
