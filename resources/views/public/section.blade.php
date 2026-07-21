@extends('layouts.public')

@section('title', $title.' | Almacenes al Costo')
@section('description', $description)

@section('content')
    <section class="public-section-placeholder" aria-labelledby="section-title">
        <div class="container py-5 py-lg-6">
            <div class="section-placeholder-card p-4 p-md-5">
                <p class="section-eyebrow mb-2">Almacenes al Costo</p>
                <h1 class="display-5 fw-bold mb-3" id="section-title">{{ $title }}</h1>
                <p class="lead mb-4">{{ $message }}</p>
                <a class="btn btn-brand" href="{{ route('home') }}">Volver al inicio</a>
            </div>
        </div>
    </section>
@endsection
