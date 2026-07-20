@extends('layouts.admin')
@section('title', 'Dashboard | Almacenes al Costo')
@php($breadcrumbs = [['label' => 'Dashboard']])
@section('content')
    <x-shared.page-header title="Dashboard" subtitle="Bienvenido al espacio administrativo de Almacenes al Costo." />
    <div class="alert alert-warning" role="note">Los valores mostrados son únicamente demostrativos y no representan información real.</div>
    <div class="row g-3 mb-4">
        @foreach ([['Ventas', '$ 0,00'], ['Pedidos', '0'], ['Clientes', '0'], ['Productos', '0']] as [$label, $value])
            <div class="col-sm-6 col-xl-3"><article class="card metric-card h-100"><div class="card-body"><h2 class="h6 text-body-secondary">{{ $label }}</h2><p class="metric-card__value mb-0">{{ $value }}</p><small>Dato temporal</small></div></article></div>
        @endforeach
    </div>
    <section class="card metric-card"><div class="card-body p-4"><h2 class="h4">Resumen operativo</h2><p class="mb-0">Este espacio alojará indicadores y accesos rápidos cuando los módulos estén disponibles.</p></div></section>
@endsection
