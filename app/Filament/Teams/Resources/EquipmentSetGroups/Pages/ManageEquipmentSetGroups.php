<?php

namespace App\Filament\Teams\Resources\EquipmentSetGroups\Pages;

use App\Filament\Teams\Resources\EquipmentSetGroups\EquipmentSetGroupResource;
use App\Filament\Teams\Resources\EquipmentSets\EquipmentSetResource;
use App\Models\EquipmentSetGroup;
use App\Models\Team;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;

class ManageEquipmentSetGroups extends ManageRecords
{
    protected static string $resource = EquipmentSetGroupResource::class;

    /**
     * @return array<string, string|null>
     */
    public function getBreadcrumbs(): array
    {
        return [
            EquipmentSetResource::getUrl() => EquipmentSetResource::getNavigationLabel(),
            ...parent::getBreadcrumbs(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $team = Filament::getTenant();

                    if (! $team instanceof Team) {
                        return $data;
                    }

                    EquipmentSetGroup::ensureDefaultFor($team);

                    $data['team_id'] = $team->getKey();
                    $data['rank'] = EquipmentSetGroup::nextRankFor($team);

                    return $data;
                }),
        ];
    }

    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        parent::reorderTable($order, $draggedRecordKey);

        $team = Filament::getTenant();

        if ($team instanceof Team) {
            EquipmentSetGroup::renumberForTeam($team);
        }
    }
}
