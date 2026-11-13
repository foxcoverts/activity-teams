<?php

namespace App\Filament\Teams\Resources\EquipmentSets\Pages;

use App\Filament\Teams\Resources\EquipmentSets\EquipmentSetResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateEquipmentSet extends CreateRecord
{
    protected static string $resource = EquipmentSetResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['team_id'] = Filament::getTenant()?->getKey();

        return $data;
    }
}
