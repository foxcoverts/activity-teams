<?php

namespace App\Filament\Manage\Resources\Activities\Pages;

use App\Filament\Manage\Resources\Activities\Actions\GiveActivityAction;
use App\Filament\Manage\Resources\Activities\ActivityResource;
use Filament\Resources\Pages\ListRecords;

class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GiveActivityAction::make(),
        ];
    }
}
