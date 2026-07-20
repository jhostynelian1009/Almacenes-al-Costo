<nav class="navbar navbar-expand-lg public-navbar sticky-top" aria-label="Navegación principal">
    <div class="container">
        <a class="navbar-brand brand-mark" href="{{ route('home') }}">Almacenes al Costo</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavigation" aria-controls="publicNavigation" aria-expanded="false" aria-label="Mostrar navegación"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="publicNavigation"><ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
            <li class="nav-item"><a class="nav-link active" aria-current="page" href="{{ route('home') }}">Inicio</a></li>
            @foreach (['Catálogo', 'Promociones', 'Contacto'] as $item)<li class="nav-item"><span class="nav-link disabled" aria-disabled="true">{{ $item }}</span></li>@endforeach
            <li class="nav-item"><span class="nav-link disabled" aria-disabled="true" aria-label="Carrito disponible próximamente">Carrito (próximamente)</span></li>
        </ul></div>
    </div>
</nav>
