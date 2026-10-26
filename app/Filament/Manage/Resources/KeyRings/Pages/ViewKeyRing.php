<?php

namespace App\Filament\Manage\Resources\KeyRings\Pages;

use App\Filament\KeyRings\PassKeyRingAction;
use App\Filament\Manage\Resources\KeyRings\KeyRingResource;
use Filament\Resources\Pages\ViewRecord;

class ViewKeyRing extends ViewRecord
{
    protected static string $resource = KeyRingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PassKeyRingAction::make(),
        ];
    }
}
