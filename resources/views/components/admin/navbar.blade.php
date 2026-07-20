<nav class="navbar admin-navbar navbar-dark sticky-top px-3" aria-label="Barra superior administrativa">
    <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-controls="adminSidebar" aria-label="Abrir menú administrativo"><span class="navbar-toggler-icon"></span></button>
    <a class="navbar-brand fw-bold me-auto ms-2 ms-lg-0" href="{{ route('admin.dashboard') }}">Almacenes al Costo</a>
    <div class="d-flex align-items-center gap-2 text-white">
        <span class="small d-none d-sm-inline" aria-label="Notificaciones provisionales">Sin notificaciones</span>
        <span class="badge rounded-pill text-bg-light">{{ auth()->user()->name }}</span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn btn-sm btn-outline-light" type="submit">Cerrar sesión</button>
        </form>
    </div>
</nav>
