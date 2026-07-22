@php
    $categoryKey = (string) $category->getKey();
    $isRepeated = in_array($categoryKey, $visited, true);
    $nextVisited = [...$visited, $categoryKey];
    $children = $isRepeated || $depth >= $maxDepth
        ? collect()
        : $categoriesByParent->get($categoryKey, collect());
@endphp

@if (! $isRepeated && $depth < $maxDepth)
    <li class="{{ $depth > 0 ? 'mt-2' : 'mb-3' }}">
        <article class="border rounded-3 p-3" aria-labelledby="category-node-{{ $category->getKey() }}">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div>
                    <h2 class="h6 mb-2" id="category-node-{{ $category->getKey() }}">{{ $category->name }}</h2>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge text-bg-light border">Nivel {{ $depth + 1 }}</span>
                        <span class="badge {{ $category->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $category->is_active ? 'Activa' : 'Inactiva' }}
                        </span>
                        <span class="badge text-bg-light border">Orden {{ $category->display_order }}</span>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.categories.show', $category) }}">Ver {{ $category->name }}</a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.categories.edit', $category) }}">Editar {{ $category->name }}</a>
                </div>
            </div>
        </article>

        @if ($children->isNotEmpty())
            <ul class="list-unstyled ms-3 ms-md-4 border-start ps-3" aria-label="Subcategorías de {{ $category->name }}">
                @foreach ($children as $child)
                    @include('admin.categories._tree-node', [
                        'category' => $child,
                        'categoriesByParent' => $categoriesByParent,
                        'visited' => $nextVisited,
                        'depth' => $depth + 1,
                        'maxDepth' => $maxDepth,
                    ])
                @endforeach
            </ul>
        @endif
    </li>
@endif
