@props([
    'eyebrow',
    'title',
    'headingId',
    'description' => null,
])

<header {{ $attributes->class(['section-heading']) }}>
    <p class="section-eyebrow mb-2">{{ $eyebrow }}</p>
    <h2 class="display-6 fw-bold mb-2" id="{{ $headingId }}">{{ $title }}</h2>
    @if ($description)
        <p class="section-heading__description mb-0">{{ $description }}</p>
    @endif
</header>
