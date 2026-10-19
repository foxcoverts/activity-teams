<?php

namespace App\Filament\Manage\Resources\KeyRings\Pages;

use App\Filament\Manage\Resources\KeyRings\KeyRingResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewKeyRing extends ViewRecord
{
    protected static string $resource = KeyRingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
