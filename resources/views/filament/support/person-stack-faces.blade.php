@props([
    'people' => [],
    'overflow' => 0,
    'size' => 32,
    'ring' => 2,
    'overlap' => 2,
])

@php
    $height = "{$size}px";
@endphp

<div
    {{ $attributes->class([
        'fi-ta-image',
        'fi-inline', // Nested inside the column cell — avoid Filament image column padding.
        'fi-circular',
        'fi-stacked',
        "fi-ta-image-ring fi-ta-image-ring-{$ring}" => filled($ring),
        "fi-ta-image-overlap-{$overlap}" => filled($overlap),
    ]) }}
>
    @foreach ($people as $person)
        <img
            src="{{ $person['avatar_url'] }}"
            alt="{{ $person['name'] }}"
            x-tooltip="{
                content: @js($person['name']),
                theme: $store.theme,
                allowHTML: false,
            }"
            style="height: {{ $height }}; width: {{ $height }}"
        />
    @endforeach

    @if ($overflow > 0)
        <div
            class="fi-ta-image-limited-remaining-text"
            style="height: {{ $height }}; width: {{ $height }}"
            x-tooltip="{
                content: @js('+' . $overflow),
                theme: $store.theme,
                allowHTML: false,
            }"
        >
            +{{ $overflow }}
        </div>
    @endif
</div>
