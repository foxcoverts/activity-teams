@props([
    'disabled' => false,
])

<div class="relative mt-1">
    <select
        {{ $attributes->merge([
            'class' =>
                'w-full appearance-none rounded border bg-white py-2 pl-3 pr-10 disabled:bg-slate-50 disabled:text-slate-500',
        ]) }}
        @disabled($disabled)>
        {{ $slot }}
    </select>
    <svg class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-slate-500" viewBox="0 0 20 20"
        fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd"
            d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z"
            clip-rule="evenodd" />
    </svg>
</div>
