<?php

namespace App\Filament\Admin\Resources\Locks\Pages;

use App\Filament\Admin\Resources\Locks\LockResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationGroup;

class ViewLock extends ViewRecord
{
    protected static string $resource = LockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
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

        $record = $this->getRecord();

        foreach ($managers as $key => $manager) {
            if ($manager instanceof RelationGroup) {
                continue;
            }

            /** @var class-string $class */
            $class = $this->normalizeRelationManagerClass($manager);

            if ($record->{$class::getRelationshipName()}()->exists()) {
                $this->activeRelationManager = $key;

                return;
            }
        }

        $this->activeRelationManager = array_key_first($managers);
    }
}
