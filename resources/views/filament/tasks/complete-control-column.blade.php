@php
    use App\Models\Task;

    /** @var Task $record */
    $record = $getRecord();
    $canToggle = auth()->user()?->can('complete', $record) ?? false;
@endphp

<div class="flex justify-center" wire:key="task-complete-{{ $record->getKey() }}-{{ $record->status->value }}">
    @include('filament.tasks.complete-control', [
        'task' => $record,
        'canToggle' => $canToggle,
    ])
</div>
