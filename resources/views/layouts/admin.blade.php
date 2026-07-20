@extends('layouts.app')

@push('styles')
    @vite('resources/css/admin.css')
@endpush

@section('body')
    <x-admin.navbar />
    <div class="admin-shell d-lg-flex">
        <aside class="admin-sidebar d-none d-lg-block p-3" aria-label="Navegación administrativa"><x-admin.sidebar /></aside>
        <div class="offcanvas offcanvas-start admin-sidebar" tabindex="-1" id="adminSidebar" aria-labelledby="adminSidebarLabel">
            <div class="offcanvas-header"><h2 class="offcanvas-title h5" id="adminSidebarLabel">Menú administrativo</h2><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar menú"></button></div>
            <div class="offcanvas-body"><x-admin.sidebar /></div>
        </div>
        <div class="admin-main d-flex flex-column">
            <main id="main-content" class="container-fluid p-3 p-md-4 flex-grow-1">
                <x-shared.alerts />
                <x-shared.breadcrumbs :items="$breadcrumbs ?? []" />
                @yield('content')
            </main>
            <x-admin.footer />
        </div>
    </div>
@endsection
