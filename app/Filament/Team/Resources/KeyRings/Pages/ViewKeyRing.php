<?php

namespace App\Filament\Team\Resources\KeyRings\Pages;

use App\Filament\Team\Resources\KeyRings\KeyRingResource;
use Filament\Resources\Pages\ViewRecord;

class ViewKeyRing extends ViewRecord
{
    protected static string $resource = KeyRingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
