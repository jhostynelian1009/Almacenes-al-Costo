@props([
    'title',
    'message',
    'name',
    'headingLevel' => 3,
])

<div {{ $attributes->class(['public-empty-state text-center']) }} data-empty-state="{{ $name }}" role="status">
    <div class="public-empty-state__mark" aria-hidden="true">AC</div>
    @if ((int) $headingLevel === 2)
        <h2 class="h5 mb-2">{{ $title }}</h2>
    @else
        <h3 class="h5 mb-2">{{ $title }}</h3>
    @endif
    <p class="mb-0">{{ $message }}</p>

    @if (! $slot->isEmpty())
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
