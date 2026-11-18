@props(['priority', 'size' => 'size-4'])

@if ($priority instanceof \App\Enums\TaskPriority)
    <span
        {{ $attributes->class(['inline-flex shrink-0 text-color-600 dark:text-color-400']) }}
        title="{{ $priority->getLabel() }}"
        @style([
            \Filament\Support\get_color_css_variables(
                $priority->getColor(),
                shades: [400, 600],
            ),
        ])
    >
        <x-filament::icon :icon="$priority->getIcon()" @class([$size]) />
        <span class="sr-only">{{ $priority->getLabel() }}</span>
    </span>
@endif
