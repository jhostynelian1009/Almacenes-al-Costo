@props(['title', 'subtitle' => null])
<header {{ $attributes->class(['d-md-flex align-items-start justify-content-between gap-3 mb-4']) }}>
    <div><h1 class="h2 mb-1">{{ $title }}</h1>@if ($subtitle)<p class="text-body-secondary mb-0">{{ $subtitle }}</p>@endif</div>
    @if (!$slot->isEmpty())<div class="mt-3 mt-md-0">{{ $slot }}</div>@endif
</header>
