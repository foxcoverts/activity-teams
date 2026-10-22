<?php

namespace App\Filament\Member\Resources\KeyRings\Pages;

use App\Filament\Member\Resources\KeyRings\KeyRingResource;
use Filament\Resources\Pages\ViewRecord;

class ViewKeyRing extends ViewRecord
{
    protected static string $resource = KeyRingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
