@props(['title', 'overdue' => false, 'heading' => false, 'priority' => null])

<span @class([
    'inline-flex min-w-0 flex-wrap items-center gap-2',
    'fi-header-heading text-2xl font-semibold tracking-tight text-gray-950 dark:text-white' => $heading,
])>
    @include('filament.tasks.priority-icon', [
        'priority' => $priority,
        'size' => $heading ? 'size-6' : 'size-4',
    ])
    <span class="min-w-0">{{ $title }}</span>
    @if ($overdue)
        <x-filament::badge :color="\App\Support\Palette::destructive()" size="sm">
            {{ __('Overdue') }}
        </x-filament::badge>
    @endif
</span>
