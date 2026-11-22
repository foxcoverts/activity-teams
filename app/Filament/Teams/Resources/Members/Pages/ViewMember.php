<?php

namespace App\Filament\Teams\Resources\Members\Pages;

use App\Filament\Concerns\AcknowledgesPersonalData;
use App\Filament\RelationManagers\QualificationsRelationManager;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Support\Icons\Heroicon;

class ViewMember extends ViewRecord
{
    use AcknowledgesPersonalData;

    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        abort_unless($record instanceof User, 404);

        return [
            MemberResource::invitationActions($record),
            ActionGroup::make([
                EditAction::make(),
                MemberResource::changeRoleAction($record),
                MemberResource::removeFromTeamAction($record),
            ])
                ->label(__('Manage'))
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->button(),
        ];
    }

    public function renderingHasRelationManagers(): void
    {
        $managers = $this->getCachedRelationManagers();

        if (array_key_exists($this->activeRelationManager ?? '', $managers)) {
            return;
        }

        if ($this->hasCombinedRelationManagerTabsWithContent()) {
            return;
        }

        foreach ($managers as $key => $manager) {
            if ($manager instanceof RelationGroup) {
                continue;
            }

            /** @var class-string $class */
            $class = $this->normalizeRelationManagerClass($manager);

            if ($class === QualificationsRelationManager::class) {
                $this->activeRelationManager = $key;

                return;
            }
        }

        $this->activeRelationManager = array_key_first($managers);
    }
}
