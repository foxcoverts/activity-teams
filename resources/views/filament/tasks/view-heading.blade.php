@php
    use App\Models\Task;

    /** @var Task $task */
@endphp

<div class="mb-4 flex items-start gap-3" wire:key="task-heading-{{ $task->getKey() }}-{{ $task->status->value }}">
    @include('filament.tasks.complete-control', [
        'task' => $task,
        'canToggle' => $canToggle,
    ])
    <div class="min-w-0">
        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">{{ $task->summary }}</h2>
        @if ($task->isOverdue())
            <p class="mt-1 text-sm text-danger-600 dark:text-danger-400">{{ __('Overdue') }}</p>
        @endif
    </div>
</div>
