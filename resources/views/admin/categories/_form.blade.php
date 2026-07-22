<div class="mb-3">
    <label class="form-label" for="name">Nombre</label>
    <input
        class="form-control @error('name') is-invalid @enderror @error('slug') is-invalid @enderror"
        id="name"
        name="name"
        type="text"
        value="{{ old('name', $category?->name) }}"
        maxlength="255"
        required
        autofocus
        aria-describedby="name-help @error('name') name-error @enderror @error('slug') slug-error @enderror"
    >
    <div class="form-text" id="name-help">El slug se genera automáticamente a partir del nombre.</div>
    @error('name')
        <div class="invalid-feedback" id="name-error">{{ $message }}</div>
    @enderror
    @error('slug')
        <div class="invalid-feedback" id="slug-error">Ya existe una categoría con el mismo slug generado.</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="description">Descripción <span class="text-body-secondary">(opcional)</span></label>
    <textarea
        class="form-control @error('description') is-invalid @enderror"
        id="description"
        name="description"
        rows="4"
        aria-describedby="@error('description') description-error @enderror"
    >{{ old('description', $category?->description) }}</textarea>
    @error('description')
        <div class="invalid-feedback" id="description-error">{{ $message }}</div>
    @enderror
</div>

<div class="mb-4">
    <label class="form-label" for="parent_id">Categoría padre <span class="text-body-secondary">(opcional)</span></label>
    <select
        class="form-select @error('parent_id') is-invalid @enderror"
        id="parent_id"
        name="parent_id"
        aria-describedby="parent-help @error('parent_id') parent-error @enderror"
    >
        <option value="">Sin categoría padre</option>
        @foreach ($parentCategories as $parentCategory)
            <option value="{{ $parentCategory->id }}" @selected((string) old('parent_id', $category?->parent_id) === (string) $parentCategory->id)>
                {{ $parentCategory->name }}
            </option>
        @endforeach
    </select>
    <div class="form-text" id="parent-help">Déjala vacía para crear una categoría principal.</div>
    @error('parent_id')
        <div class="invalid-feedback" id="parent-error">{{ $message }}</div>
    @enderror
</div>
