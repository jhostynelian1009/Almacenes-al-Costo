@extends('layouts.admin')

@section('title', 'Editar categoría')
@php($breadcrumbs = [
    ['label' => 'Categorías', 'url' => route('admin.categories.index')],
    ['label' => $category->name, 'url' => route('admin.categories.show', $category)],
    ['label' => 'Editar'],
])

@section('content')
    <x-shared.page-header :title="'Editar '.$category->name" subtitle="Actualiza los datos administrables de la categoría." />

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.categories.update', $category) }}">
                @csrf
                @method('PUT')
                @include('admin.categories._form')

                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit">Guardar cambios</button>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.categories.show', $category) }}">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
