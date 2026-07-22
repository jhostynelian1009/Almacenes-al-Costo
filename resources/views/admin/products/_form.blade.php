<div class="mb-3">
    <label class="form-label" for="name">Nombre</label>
    <input
        class="form-control @error('name') is-invalid @enderror"
        id="name"
        name="name"
        type="text"
        value="{{ old('name', $product?->name) }}"
        maxlength="255"
        required
        autofocus
        aria-describedby="@error('name') name-error @enderror"
    >
    @error('name')
        <div class="invalid-feedback" id="name-error">{{ $message }}</div>
    @enderror
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-md-6">
        <label class="form-label" for="sku">SKU</label>
        <input
            class="form-control @error('sku') is-invalid @enderror"
            id="sku"
            name="sku"
            type="text"
            value="{{ old('sku', $product?->sku) }}"
            maxlength="50"
            required
            aria-describedby="sku-help @error('sku') sku-error @enderror"
        >
        <div class="form-text" id="sku-help">Debe ser único y contener como máximo 50 caracteres.</div>
        @error('sku')
            <div class="invalid-feedback" id="sku-error">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-6">
        <label class="form-label" for="price">Precio</label>
        <div class="input-group">
            <span class="input-group-text" aria-hidden="true">$</span>
            <input
                class="form-control @error('price') is-invalid @enderror"
                id="price"
                name="price"
                type="number"
                value="{{ old('price', $product?->price) }}"
                min="0"
                max="99999999.99"
                step="0.01"
                inputmode="decimal"
                required
                aria-describedby="price-help @error('price') price-error @enderror"
            >
            @error('price')
                <div class="invalid-feedback" id="price-error">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-text" id="price-help">Ingresa un valor no negativo con hasta dos decimales.</div>
    </div>
</div>

<div class="mb-3">
    <label class="form-label" for="category_id">Categoría</label>
    <select
        class="form-select @error('category_id') is-invalid @enderror"
        id="category_id"
        name="category_id"
        required
        aria-describedby="category-help @error('category_id') category-error @enderror"
    >
        <option value="">Selecciona una categoría</option>
        @foreach ($categoryOptions as $categoryOption)
            <option value="{{ $categoryOption['id'] }}" @selected((string) old('category_id', $product?->category_id) === (string) $categoryOption['id'])>
                {{ $categoryOption['label'] }}
            </option>
        @endforeach
    </select>
    <div class="form-text" id="category-help">La sangría indica el nivel de cada categoría en la jerarquía.</div>
    @error('category_id')
        <div class="invalid-feedback" id="category-error">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="description">Descripción <span class="text-body-secondary">(opcional)</span></label>
    <textarea
        class="form-control @error('description') is-invalid @enderror"
        id="description"
        name="description"
        rows="5"
        aria-describedby="@error('description') description-error @enderror"
    >{{ old('description', $product?->description) }}</textarea>
    @error('description')
        <div class="invalid-feedback" id="description-error">{{ $message }}</div>
    @enderror
</div>

<div class="form-check mb-4">
    <input type="hidden" name="is_active" value="0">
    <input
        class="form-check-input @error('is_active') is-invalid @enderror"
        id="is_active"
        name="is_active"
        type="checkbox"
        value="1"
        @checked((bool) old('is_active', $product?->is_active ?? true))
        aria-describedby="active-help @error('is_active') active-error @enderror"
    >
    <label class="form-check-label" for="is_active">Producto activo</label>
    <div class="form-text" id="active-help">Los productos inactivos permanecen administrables, pero no deben mostrarse públicamente.</div>
    @error('is_active')
        <div class="invalid-feedback" id="active-error">{{ $message }}</div>
    @enderror
</div>
