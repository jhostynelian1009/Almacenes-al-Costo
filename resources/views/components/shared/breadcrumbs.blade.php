@props(['items' => []])
@if (count($items))
    <nav aria-label="Migas de pan"><ol class="breadcrumb">
        @foreach ($items as $item)
            <li class="breadcrumb-item {{ $loop->last ? 'active' : '' }}" @if ($loop->last) aria-current="page" @endif>
                @if (!$loop->last && !empty($item['url']))<a href="{{ $item['url'] }}">{{ $item['label'] }}</a>@else{{ $item['label'] }}@endif
            </li>
        @endforeach
    </ol></nav>
@endif
