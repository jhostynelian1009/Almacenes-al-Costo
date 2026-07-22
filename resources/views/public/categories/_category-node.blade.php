@php
    $categoryId = (string) $category->id;
    $alreadyVisited = in_array($categoryId, $visitedIds, true);
    $canRender = ! $alreadyVisited && $depth < $maxDepth;
    $nextVisitedIds = [...$visitedIds, $categoryId];
    $children = $canRender
        ? $categoriesByParent->get($categoryId, collect())
        : collect();
@endphp

@if ($canRender)
    <li>
        <article class="section-placeholder-card p-3 p-md-4" aria-label="{{ $category->name }}">
            <p class="small fw-semibold text-body-secondary mb-1">Nivel {{ $depth + 1 }}</p>
            <h2 class="h5 mb-2">{{ $category->name }}</h2>

            @if (filled($category->description))
                <p class="text-body-secondary mb-0">{{ $category->description }}</p>
            @endif

            @if ($children->isNotEmpty())
                <ul class="list-unstyled d-grid gap-3 border-start ps-3 ps-md-4 mt-3 mb-0"
                    aria-label="Subcategorías de {{ $category->name }}">
                    @foreach ($children as $child)
                        @include('public.categories._category-node', [
                            'category' => $child,
                            'categoriesByParent' => $categoriesByParent,
                            'visitedIds' => $nextVisitedIds,
                            'depth' => $depth + 1,
                            'maxDepth' => $maxDepth,
                        ])
                    @endforeach
                </ul>
            @endif
        </article>
    </li>
@endif
