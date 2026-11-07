@props([
    'person',
])

@if (! blank($person['avatar_url'] ?? null))
    <img src="{{ $person['avatar_url'] }}" alt="" width="24" height="24"
        class="size-6 shrink-0 rounded-full" />
@endif
<span>{{ $person['name'] }}</span>
