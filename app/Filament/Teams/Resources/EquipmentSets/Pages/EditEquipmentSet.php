<?php

namespace App\Filament\Teams\Resources\EquipmentSets\Pages;

use App\Filament\Teams\Resources\EquipmentSets\EquipmentSetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEquipmentSet extends EditRecord
{
    protected static string $resource = EquipmentSetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
