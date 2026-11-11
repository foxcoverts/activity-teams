<?php

namespace App\Filament\Teams\Widgets;

use App\Filament\Member\Widgets\AssignedTasksWidget as MemberAssignedTasksWidget;
use App\Filament\Teams\Resources\Tasks\TaskResource;
use App\Filament\Teams\Widgets\Concerns\ScopedToTeam;
use App\Models\Task;

class AssignedTasksWidget extends MemberAssignedTasksWidget
{
    use ScopedToTeam;

    protected function sectionData(): array
    {
        return [
            ...parent::sectionData(),
            'tasksUrl' => TaskResource::getUrl('index'),
            'viewUrl' => fn (Task $task): string => TaskResource::getUrl('view', ['record' => $task]),
            'showTeam' => false,
        ];
    }
}
