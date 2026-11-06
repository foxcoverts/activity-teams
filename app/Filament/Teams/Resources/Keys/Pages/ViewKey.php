<?php

namespace App\Filament\Teams\Resources\Keys\Pages;

use App\Filament\Keys\PassKeyAction;
use App\Filament\Teams\Resources\Keys\KeyResource;
use Filament\Resources\Pages\ViewRecord;

class ViewKey extends ViewRecord
{
    protected static string $resource = KeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PassKeyAction::make(),
        ];
    }
}
