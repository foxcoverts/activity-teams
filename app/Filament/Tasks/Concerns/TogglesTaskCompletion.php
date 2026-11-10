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

        $wasComplete = $task->isComplete();
        $task->toggleCompletion();

        Notification::make()
            ->success()
            ->title($wasComplete ? __('Marked incomplete.') : __('Marked complete.'))
            ->send();
    }
}
