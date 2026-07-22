@extends('layouts.admin')

@section('title', 'Crear producto')
@php($breadcrumbs = [
    ['label' => 'Productos', 'url' => route('admin.products.index')],
    ['label' => 'Crear'],
])

@section('content')
    <x-shared.page-header title="Crear producto" subtitle="Registra la información comercial del producto." />

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.products.store') }}">
                @csrf
                @include('admin.products._form', ['product' => null])

                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit">Guardar producto</button>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.products.index') }}">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
