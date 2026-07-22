@extends('layouts.admin')

@section('title', 'Editar producto')
@php($breadcrumbs = [
    ['label' => 'Productos', 'url' => route('admin.products.index')],
    ['label' => $product->name, 'url' => route('admin.products.show', $product)],
    ['label' => 'Editar'],
])

@section('content')
    <x-shared.page-header :title="'Editar '.$product->name" subtitle="Actualiza los datos administrables del producto." />

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.products.update', $product) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('admin.products._form')

                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit">Guardar cambios</button>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.products.show', $product) }}">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
