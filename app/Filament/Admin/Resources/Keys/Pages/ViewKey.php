<?php

namespace App\Filament\Admin\Resources\Keys\Pages;

use App\Filament\Admin\Resources\Keys\KeyResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewKey extends ViewRecord
{
    protected static string $resource = KeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
