@php
    use App\Enums\TaskAttendeeStatus;
    use Filament\Support\Icons\Heroicon;

    $showTeam ??= true;
    $response = $task->attendance?->status;
    $overdue = $task->isOverdue();
    $chip =
        'inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-sm text-gray-700 dark:bg-white/10 dark:text-gray-300';
    $overdueChip =
        'inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-sm text-red-700 dark:bg-red-400/10 dark:text-red-400';
    $due = $task->due_at !== null
        ? localDate($task->due_at, auth()->user()?->timezoneName() ?? (string) config('app.timezone'))->isoFormat('ddd D MMM HH:mm')
        : null;
    if ($due !== null && $overdue) {
        $due .= ' – '.__('overdue');
    }
@endphp

<li class="relative flex items-start gap-3 py-4 first:pt-0 last:pb-0" wire:key="task-{{ $task->getKey() }}">
    <div class="relative z-10 shrink-0 pt-0.5">
        @include('filament.tasks.complete-control', [
            'task' => $task,
            'canToggle' => $canToggle,
        ])
    </div>

    <div class="relative min-w-0 flex-1 space-y-2">
        <p class="text-base font-semibold">
            <a href="{{ $viewUrl }}"
                class="hover:underline after:absolute after:inset-0 after:z-0 after:content-['']">{{ $task->summary }}</a>
        </p>

        <div class="flex flex-wrap items-center gap-2">
            @if ($due)
                <span class="{{ $overdue ? $overdueChip : $chip }}"><x-filament::icon :icon="Heroicon::OutlinedClock"
                        class="size-4" />{{ $due }}</span>
            @endif
            @if ($task->priority)
                <span
                    class="inline-flex items-center gap-1.5 rounded-full bg-color-50 px-2.5 py-1 text-sm text-color-600 dark:bg-color-400/10 dark:text-color-400"
                    @style([
                        \Filament\Support\get_color_css_variables(
                            $task->priority->getColor(),
                            shades: [50, 400, 600],
                        ),
                    ])
                >
                    <x-filament::icon :icon="$task->priority->getIcon()" class="size-4" />{{ $task->priority->getLabel() }}
                </span>
            @endif
            @if ($showTeam && $task->team)
                <span
                    class="inline-flex items-center gap-1.5 rounded-full bg-color-50 px-2.5 py-1 text-sm text-color-600 dark:bg-color-400/10 dark:text-color-400"
                    @style([
                        \Filament\Support\get_color_css_variables(
                            $task->team->brandColor()->getColor(),
                            shades: [50, 400, 600],
                        ),
                    ])
                >
                    <x-filament::icon :icon="Heroicon::OutlinedUserGroup" class="size-4" />{{ $task->team->name }}
                </span>
            @endif
            @if ($response === TaskAttendeeStatus::NeedsAction)
                <span class="{{ $chip }}"><x-filament::icon :icon="$response->getIcon()"
                        class="size-4" />{{ $response->getLabel() }}</span>
            @endif
        </div>
    </div>
</li>
