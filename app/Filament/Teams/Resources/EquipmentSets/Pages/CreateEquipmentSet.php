<?php

namespace App\Filament\Teams\Resources\EquipmentSets\Pages;

use App\Filament\Teams\Resources\EquipmentSets\EquipmentSetResource;
use App\Models\EquipmentSetGroup;
use App\Models\Team;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateEquipmentSet extends CreateRecord
{
    protected static string $resource = EquipmentSetResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $team = Filament::getTenant();

        if ($team instanceof Team) {
            EquipmentSetGroup::ensureDefaultFor($team);
            $data['team_id'] = $team->getKey();
            $data['equipment_set_group_id'] = $data['equipment_set_group_id']
                ?? EquipmentSetGroup::ensureDefaultFor($team)->getKey();
        }

        return $data;
    }
}
