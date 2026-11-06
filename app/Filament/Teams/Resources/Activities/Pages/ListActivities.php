<?php

namespace App\Filament\Teams\Resources\Activities\Pages;

use App\Filament\Teams\Resources\Activities\Actions\GiveActivityAction;
use App\Filament\Teams\Resources\Activities\ActivityResource;
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
