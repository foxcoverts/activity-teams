<?php

namespace App\Filament\Tasks\Concerns;

use App\Models\Task;
use Filament\Notifications\Notification;

trait TogglesTaskCompletion
{
    public function toggleTaskComplete(string $taskId): void
    {
        $task = Task::query()->findOrFail($taskId);
        $user = auth()->user();

        abort_unless($user?->can('complete', $task) ?? false, 403);

        $wasCompleteOrCancelled = $task->isComplete() || $task->isCancelled();
        $task->toggleCompletion();

        if (property_exists($this, 'record') && $this->record instanceof Task && (string) $this->record->getKey() === $taskId) {
            $this->record = $task->fresh();
        }

        if (method_exists($this, 'flushCachedTableRecords')) {
            $this->flushCachedTableRecords();
        }

        Notification::make()
            ->success()
            ->title($wasCompleteOrCancelled ? __('Marked incomplete.') : __('Marked complete.'))
            ->send();
    }
}
