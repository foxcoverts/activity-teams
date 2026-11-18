@props(['title', 'overdue' => false, 'heading' => false, 'priority' => null])

<span @class([
    'inline-flex min-w-0 flex-wrap items-center gap-2',
    'fi-header-heading text-2xl font-semibold tracking-tight text-gray-950 dark:text-white' => $heading,
])>
    @if ($priority instanceof \App\Enums\TaskPriority)
        <span
            class="inline-flex shrink-0 text-color-600 dark:text-color-400"
            title="{{ $priority->getLabel() }}"
            @style([
                \Filament\Support\get_color_css_variables(
                    $priority->getColor(),
                    shades: [400, 600],
                ),
            ])
        >
            <x-filament::icon :icon="$priority->getIcon()" class="size-4" />
            <span class="sr-only">{{ $priority->getLabel() }}</span>
        </span>
    @endif
    <span class="min-w-0">{{ $title }}</span>
    @if ($overdue)
        <x-filament::badge :color="\App\Support\Palette::destructive()" size="sm">
            {{ __('Overdue') }}
        </x-filament::badge>
    @endif
</span>
