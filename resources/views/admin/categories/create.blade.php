@extends('layouts.admin')

@section('title', 'Crear categoría')
@php($breadcrumbs = [
    ['label' => 'Categorías', 'url' => route('admin.categories.index')],
    ['label' => 'Crear'],
])

@section('content')
    <x-shared.page-header title="Crear categoría" subtitle="Registra una categoría principal o una subcategoría." />

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.categories.store') }}">
                @csrf
                @include('admin.categories._form', ['category' => null])

                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit">Guardar categoría</button>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.categories.index') }}">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
