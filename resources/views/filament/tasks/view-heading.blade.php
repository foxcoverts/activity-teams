@php
    use App\Models\Task;

    /** @var Task $task */
@endphp

<div wire:key="task-heading-{{ $task->getKey() }}-{{ $task->status->value }}-{{ $task->updated_at?->timestamp }}">
    <div class="flex items-center gap-3">
        @include('filament.tasks.complete-control', [
            'task' => $task,
            'canToggle' => $canToggle,
        ])
        @include('filament.tasks.title-with-overdue', [
            'title' => $task->summary,
            'overdue' => $task->isOverdue(),
            'heading' => true,
        ])
    </div>
</div>
