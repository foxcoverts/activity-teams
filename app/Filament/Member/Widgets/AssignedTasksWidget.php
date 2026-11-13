<?php

namespace App\Filament\Member\Widgets;

use App\Filament\Member\Resources\Tasks\TaskResource;
use App\Filament\Tasks\Concerns\TogglesTaskCompletion;
use App\Models\Task;

class AssignedTasksWidget extends DashboardSection
{
    use TogglesTaskCompletion {
        toggleTaskComplete as protected toggleTaskCompleteBase;
    }

    protected static ?int $sort = 5;

    protected string $view = 'filament.member.widgets.assigned-tasks';

    protected function sectionData(): array
    {
        return [
            ...$this->dashboard()->assignedTasks(),
            'tasksUrl' => TaskResource::getUrl('index'),
            'canToggle' => fn (Task $task): bool => (bool) (auth()->user()?->can('complete', $task)),
            'viewUrl' => fn (Task $task): string => TaskResource::viewUrl($task),
        ];
    }

    public function toggleTaskComplete(string $taskId): void
    {
        $this->toggleTaskCompleteBase($taskId);
        $this->dispatch('dashboard-updated');
    }
}
