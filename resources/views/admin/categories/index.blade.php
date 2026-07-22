@extends('layouts.admin')

@section('title', 'Categorías')
@php($breadcrumbs = [['label' => 'Categorías']])

@section('content')
    <x-shared.page-header title="Categorías" subtitle="Administra las categorías y sus relaciones jerárquicas.">
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-primary" href="{{ route('admin.categories.tree') }}">Vista de árbol</a>
            <a class="btn btn-primary" href="{{ route('admin.categories.create') }}">Crear categoría</a>
        </div>
    </x-shared.page-header>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.categories.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-lg-4">
                        <label class="form-label" for="search">Buscar por nombre</label>
                        <input class="form-control @error('search') is-invalid @enderror" id="search" name="search" type="search" value="{{ old('search', $filters['search'] ?? '') }}" maxlength="100" aria-describedby="@error('search') search-error @enderror">
                        @error('search')<div class="invalid-feedback" id="search-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-sm-6 col-lg-2">
                        <label class="form-label" for="status">Estado</label>
                        <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" aria-describedby="@error('status') status-error @enderror">
                            <option value="">Todos</option>
                            <option value="active" @selected(old('status', $filters['status'] ?? '') === 'active')>Activas</option>
                            <option value="inactive" @selected(old('status', $filters['status'] ?? '') === 'inactive')>Inactivas</option>
                        </select>
                        @error('status')<div class="invalid-feedback" id="status-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <label class="form-label" for="parent_id">Categoría padre</label>
                        <select class="form-select @error('parent_id') is-invalid @enderror" id="parent_id" name="parent_id" aria-describedby="@error('parent_id') parent-filter-error @enderror">
                            <option value="">Todas</option>
                            @foreach ($parentCategories as $parentCategory)
                                <option value="{{ $parentCategory->id }}" @selected((string) old('parent_id', $filters['parent_id'] ?? '') === (string) $parentCategory->id)>{{ $parentCategory->name }}</option>
                            @endforeach
                        </select>
                        @error('parent_id')<div class="invalid-feedback" id="parent-filter-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <label class="form-label" for="level">Nivel jerárquico</label>
                        <select class="form-select @error('level') is-invalid @enderror" id="level" name="level" aria-describedby="@error('level') level-error @enderror">
                            <option value="">Todos</option>
                            <option value="main" @selected(old('level', $filters['level'] ?? '') === 'main')>Principales</option>
                            <option value="child" @selected(old('level', $filters['level'] ?? '') === 'child')>Subcategorías</option>
                        </select>
                        @error('level')<div class="invalid-feedback" id="level-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button class="btn btn-primary" type="submit">Filtrar</button>
                        <a class="btn btn-outline-secondary" href="{{ route('admin.categories.index') }}">Limpiar filtros</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if ($hasActiveFilters)
        <p class="text-body-secondary" role="status">{{ $categories->total() }} {{ $categories->total() === 1 ? 'resultado encontrado' : 'resultados encontrados' }}.</p>
    @endif

    <div class="card shadow-sm">
        <div class="card-body p-0">
            @if ($categories->isEmpty())
                <div class="p-4 text-center" role="status">
                    @if ($hasCategories)
                        <h2 class="h5">No hay categorías que coincidan</h2>
                        <p class="text-body-secondary mb-3">Prueba con otros criterios o limpia los filtros aplicados.</p>
                        <a class="btn btn-outline-secondary" href="{{ route('admin.categories.index') }}">Limpiar filtros</a>
                    @else
                        <h2 class="h5">No hay categorías registradas</h2>
                        <p class="text-body-secondary mb-3">Crea la primera categoría para comenzar a organizar los productos.</p>
                        <a class="btn btn-primary" href="{{ route('admin.categories.create') }}">Crear categoría</a>
                    @endif
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Nombre</th>
                                <th scope="col">Slug</th>
                                <th scope="col">Categoría padre</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Orden</th>
                                <th scope="col" class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($categories as $category)
                                <tr>
                                    <th scope="row">{{ $category->name }}</th>
                                    <td><code>{{ $category->slug }}</code></td>
                                    <td>{{ $category->parent?->name ?? 'Principal' }}</td>
                                    <td>
                                        <span class="badge {{ $category->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $category->is_active ? 'Activa' : 'Inactiva' }}
                                        </span>
                                    </td>
                                    <td>{{ $category->display_order }}</td>
                                    <td>
                                        <div class="d-flex flex-wrap justify-content-end gap-2">
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.categories.show', $category) }}">Ver</a>
                                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.categories.edit', $category) }}">Editar</a>
                                            <form method="POST" action="{{ route('admin.categories.status', $category) }}" onsubmit="return confirm('¿Confirmas que deseas {{ $category->is_active ? 'desactivar' : 'activar' }} esta categoría?');">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="is_active" value="{{ $category->is_active ? '0' : '1' }}">
                                                <button class="btn btn-sm {{ $category->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" type="submit">
                                                    {{ $category->is_active ? 'Desactivar' : 'Activar' }}
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('¿Confirmas que deseas eliminar esta categoría?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if ($categories->hasPages())
        <div class="mt-4">
            {{ $categories->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    @endif
@endsection
