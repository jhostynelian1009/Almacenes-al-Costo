@php($items = ['Productos', 'Categorías', 'Inventario', 'Pedidos', 'Clientes', 'Comprobantes', 'Promociones', 'Reportes', 'Usuarios', 'Configuración'])
<nav aria-label="Módulos del panel">
    <ul class="nav nav-pills flex-column gap-1">
        <li class="nav-item"><a class="nav-link active" href="{{ route('admin.dashboard') }}" aria-current="page">Dashboard</a></li>
        @foreach ($items as $item)
            <li class="nav-item"><span class="nav-link disabled" aria-disabled="true">{{ $item }} <span class="visually-hidden">(próximamente)</span></span></li>
        @endforeach
    </ul>
</nav>
