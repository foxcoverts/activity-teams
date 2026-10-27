@props([
    'url' => null,
])
@if (filled($url))
<p>
<a href="{{ $url }}" target="_blank" rel="noopener" style="color: #18181b; text-decoration: underline;">{{ $slot }}</a>
</p>
@endif
