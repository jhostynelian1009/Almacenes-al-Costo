@php($types = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info', 'status' => 'primary'])
@foreach ($types as $key => $variant)
    @if (session()->has($key))
        <div class="alert alert-{{ $variant }} alert-dismissible fade show" role="alert">{{ session($key) }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar alerta"></button></div>
    @endif
@endforeach
