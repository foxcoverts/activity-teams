@props([
    'url' => null,
])
@if (filled($url))
{{ $slot }}: {{ $url }}
@endif
