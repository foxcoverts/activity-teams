@php
    use App\Enums\TaskStatus;
    use App\Models\Task;

    /** @var Task $task */
    $cancelled = $task->status === TaskStatus::Cancelled;
    $complete = $task->isComplete() && ! $cancelled;
    $canToggle = ($canToggle ?? false) && ! $cancelled;
    $label = $complete ? __('Mark incomplete') : __('Mark complete');
@endphp

@if ($canToggle)
    <button
        type="button"
        class="task-complete-control"
        wire:click="toggleTaskComplete('{{ $task->getKey() }}')"
        wire:loading.attr="disabled"
        aria-pressed="{{ $complete ? 'true' : 'false' }}"
        aria-label="{{ $label }}"
        title="{{ $label }}"
    >
        <span class="task-complete-control__mark" aria-hidden="true"></span>
    </button>
@else
    <span
        class="task-complete-control"
        aria-pressed="{{ $complete ? 'true' : 'false' }}"
        aria-disabled="true"
        title="{{ $cancelled ? __('Cancelled') : ($complete ? __('Completed') : __('Incomplete')) }}"
    >
        <span class="task-complete-control__mark" aria-hidden="true"></span>
    </span>
@endif
