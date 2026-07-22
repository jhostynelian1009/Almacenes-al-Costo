@php
    $items = [
        ['label' => 'Productos'],
        ['label' => 'Categorías', 'route' => 'admin.categories.index', 'active' => 'admin.categories.*'],
        ['label' => 'Inventario'],
        ['label' => 'Pedidos'],
        ['label' => 'Clientes'],
        ['label' => 'Comprobantes'],
        ['label' => 'Promociones'],
        ['label' => 'Reportes'],
        ['label' => 'Usuarios'],
        ['label' => 'Configuración'],
    ];
    $isDashboard = request()->routeIs('admin.dashboard');
@endphp
<nav aria-label="Módulos del panel">
    <ul class="nav nav-pills flex-column gap-1">
        <li class="nav-item">
            <a class="nav-link {{ $isDashboard ? 'active' : '' }}" href="{{ route('admin.dashboard') }}" @if ($isDashboard) aria-current="page" @endif>Dashboard</a>
        </li>
        @foreach ($items as $item)
            <li class="nav-item">
                @if (isset($item['route']))
                    @php($isActive = request()->routeIs($item['active']))
                    <a class="nav-link {{ $isActive ? 'active' : '' }}" href="{{ route($item['route']) }}" @if ($isActive) aria-current="page" @endif>{{ $item['label'] }}</a>
                @else
                    <span class="nav-link disabled" aria-disabled="true">{{ $item['label'] }} <span class="visually-hidden">(próximamente)</span></span>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
