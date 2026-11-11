<?php

namespace App\Filament\Member\Resources\Tasks\Pages;

use App\Filament\Member\Resources\Tasks\TaskResource;
use Filament\Resources\Pages\ListRecords;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
