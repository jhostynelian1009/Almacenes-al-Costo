@extends('layouts.guest')
@section('title', 'Almacenes al Costo | Inicio')
@section('description', 'Conoce la nueva experiencia digital de Almacenes al Costo.')
@section('content')
    <section class="public-hero"><div class="container">
        <span class="badge text-bg-light mb-3">Nueva experiencia digital</span><h1 class="fw-bold">Almacenes al Costo</h1>
        <p class="lead col-lg-6">Una propuesta comercial cercana, clara y pensada para ayudarte a encontrar lo que necesitas.</p>
        <button class="btn btn-brand btn-lg" type="button" disabled aria-disabled="true">Explorar productos (próximamente)</button><p class="small mt-3 mb-0">El catálogo se encuentra en construcción.</p>
    </div></section>
    <section class="container py-5" aria-labelledby="benefits-title"><h2 id="benefits-title" class="mb-4">Una experiencia pensada para ti</h2><div class="row g-3">
        @foreach ([['Compra sencilla', 'Una navegación clara y sin complicaciones.'], ['Información útil', 'Contenido organizado para decidir con confianza.'], ['Atención cercana', 'Canales de contacto oficiales disponibles próximamente.']] as [$title, $text])
            <div class="col-md-4"><article class="benefit-card p-4"><h3 class="h5">{{ $title }}</h3><p class="mb-0">{{ $text }}</p></article></div>
        @endforeach
    </div></section>
    <section class="container pb-5"><div class="public-cta p-4 p-md-5"><h2>Estamos preparando algo mejor</h2><p class="mb-0">Muy pronto podrás conocer la oferta de Almacenes al Costo desde este espacio.</p></div></section>
@endsection
