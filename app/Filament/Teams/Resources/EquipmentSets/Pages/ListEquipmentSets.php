<?php

namespace App\Filament\Teams\Resources\EquipmentSets\Pages;

use App\Filament\Teams\Resources\EquipmentSets\EquipmentSetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEquipmentSets extends ListRecords
{
    protected static string $resource = EquipmentSetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
