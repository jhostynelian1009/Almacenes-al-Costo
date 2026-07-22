@extends('layouts.admin')

@section('title', 'Productos')
@php($breadcrumbs = [['label' => 'Productos']])

@section('content')
    <x-shared.page-header title="Productos" subtitle="Administra la información comercial de los productos.">
        <a class="btn btn-primary" href="{{ route('admin.products.create') }}">Crear producto</a>
    </x-shared.page-header>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.products.index') }}">
                <fieldset>
                    <legend class="h5 mb-3">Buscar y filtrar productos</legend>
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-lg-5">
                            <label class="form-label" for="q">Buscar por nombre o SKU</label>
                            <input
                                class="form-control @error('q') is-invalid @enderror"
                                id="q"
                                name="q"
                                type="search"
                                value="{{ old('q', $filters['q'] ?? '') }}"
                                maxlength="100"
                                aria-describedby="@error('q') q-error @enderror"
                            >
                            @error('q')
                                <div class="invalid-feedback" id="q-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-sm-6 col-lg-4">
                            <label class="form-label" for="category_id">Categoría</label>
                            <select
                                class="form-select @error('category_id') is-invalid @enderror"
                                id="category_id"
                                name="category_id"
                                aria-describedby="@error('category_id') category-filter-error @enderror"
                            >
                                <option value="">Todas</option>
                                @foreach ($categoryOptions as $categoryOption)
                                    <option value="{{ $categoryOption['id'] }}" @selected((string) old('category_id', $filters['category_id'] ?? '') === (string) $categoryOption['id'])>
                                        {{ $categoryOption['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('category_id')
                                <div class="invalid-feedback" id="category-filter-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label class="form-label" for="status">Estado</label>
                            <select
                                class="form-select @error('status') is-invalid @enderror"
                                id="status"
                                name="status"
                                aria-describedby="@error('status') status-error @enderror"
                            >
                                <option value="">Todos</option>
                                <option value="active" @selected(old('status', $filters['status'] ?? '') === 'active')>Activos</option>
                                <option value="inactive" @selected(old('status', $filters['status'] ?? '') === 'inactive')>Inactivos</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback" id="status-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button class="btn btn-primary" type="submit">Filtrar</button>
                            <a class="btn btn-outline-secondary" href="{{ route('admin.products.index') }}">Limpiar filtros</a>
                        </div>
                    </div>
                </fieldset>
            </form>
        </div>
    </div>

    @if ($hasActiveFilters)
        <p class="text-body-secondary" role="status">{{ $products->total() }} {{ $products->total() === 1 ? 'resultado encontrado' : 'resultados encontrados' }}.</p>
    @endif

    <div class="card shadow-sm">
        <div class="card-body p-0">
            @if ($products->isEmpty())
                <div class="p-4 text-center" role="status">
                    @if ($hasProducts)
                        <h2 class="h5">No hay productos que coincidan</h2>
                        <p class="text-body-secondary mb-3">Prueba con otros criterios o limpia los filtros aplicados.</p>
                        <a class="btn btn-outline-secondary" href="{{ route('admin.products.index') }}">Limpiar filtros</a>
                    @else
                        <h2 class="h5">No hay productos registrados</h2>
                        <p class="text-body-secondary mb-3">Crea el primer producto para comenzar a administrar el catálogo.</p>
                        <a class="btn btn-primary" href="{{ route('admin.products.create') }}">Crear producto</a>
                    @endif
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Imagen</th>
                                <th scope="col">Nombre</th>
                                <th scope="col">SKU</th>
                                <th scope="col">Categoría</th>
                                <th scope="col" class="text-end">Precio</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($products as $product)
                                <tr>
                                    <td>
                                        @if ($imageUrls->get($product->getKey()))
                                            <img
                                                class="img-thumbnail object-fit-cover"
                                                src="{{ asset('storage/'.$product->image) }}"
                                                alt="Imagen principal de {{ $product->name }}"
                                                width="72"
                                                height="72"
                                                loading="lazy"
                                            >
                                        @elseif ($product->image)
                                            <span class="text-body-secondary">Imagen no disponible</span>
                                        @else
                                            <span class="text-body-secondary">Sin imagen</span>
                                        @endif
                                    </td>
                                    <th scope="row">{{ $product->name }}</th>
                                    <td><code>{{ $product->sku }}</code></td>
                                    <td>{{ $product->category->name }}</td>
                                    <td class="text-end">${{ number_format((float) $product->price, 2) }}</td>
                                    <td>
                                        <span class="badge {{ $product->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $product->is_active ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap justify-content-end gap-2">
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.products.show', $product) }}">Ver</a>
                                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.products.edit', $product) }}">Editar</a>
                                            <form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('¿Confirmas que deseas eliminar lógicamente este producto?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Eliminar {{ $product->name }}">Eliminar</button>
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

    @if ($products->hasPages())
        <div class="mt-4">
            {{ $products->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    @endif
@endsection
