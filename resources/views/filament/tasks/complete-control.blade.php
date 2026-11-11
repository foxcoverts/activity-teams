@php
    use App\Enums\TaskStatus;
    use App\Models\Task;
    use Filament\Support\Icons\Heroicon;

    /** @var Task $task */
    $status = $task->status;
    $canToggle = (bool) ($canToggle ?? false);
    $pressed = $status === TaskStatus::Completed;
    $label = match ($status) {
        TaskStatus::Completed => __('Mark incomplete'),
        TaskStatus::Cancelled => __('Reopen'),
        default => __('Mark complete'),
    };
    $title = match ($status) {
        TaskStatus::NeedsAction => __('Not started'),
        TaskStatus::InProcess => __('In process'),
        TaskStatus::Completed => __('Completed'),
        TaskStatus::Cancelled => __('Cancelled'),
    };
@endphp

@if ($canToggle)
    <button
        type="button"
        class="task-complete-control"
        data-status="{{ $status->value }}"
        wire:click="toggleTaskComplete('{{ $task->getKey() }}')"
        wire:loading.attr="disabled"
        aria-pressed="{{ $pressed ? 'true' : 'false' }}"
        aria-label="{{ $label }}"
        title="{{ $label }}"
    >
        @if ($status === TaskStatus::InProcess)
            <span class="task-complete-control__mark task-complete-control__mark--dot" aria-hidden="true"></span>
        @elseif ($status === TaskStatus::Completed)
            <x-filament::icon :icon="Heroicon::Check" class="task-complete-control__icon" />
        @elseif ($status === TaskStatus::Cancelled)
            <x-filament::icon :icon="Heroicon::XMark" class="task-complete-control__icon" />
        @endif
    </button>
@else
    <span
        class="task-complete-control"
        data-status="{{ $status->value }}"
        aria-pressed="{{ $pressed ? 'true' : 'false' }}"
        aria-disabled="true"
        title="{{ $title }}"
    >
        @if ($status === TaskStatus::InProcess)
            <span class="task-complete-control__mark task-complete-control__mark--dot" aria-hidden="true"></span>
        @elseif ($status === TaskStatus::Completed)
            <x-filament::icon :icon="Heroicon::Check" class="task-complete-control__icon" />
        @elseif ($status === TaskStatus::Cancelled)
            <x-filament::icon :icon="Heroicon::XMark" class="task-complete-control__icon" />
        @endif
    </span>
@endif
