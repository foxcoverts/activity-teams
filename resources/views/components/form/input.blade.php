@props([
    'type' => 'text',
])

<input
    type="{{ $type }}"
    {{ $attributes->merge(['class' => 'mt-1 w-full rounded border bg-white px-3 py-2']) }}
>
