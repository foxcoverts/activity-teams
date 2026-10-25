<?php

namespace App\Filament\Admin\Resources\Venues\Pages;

use App\Filament\Admin\Resources\Venues\VenueResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVenue extends ViewRecord
{
    protected static string $resource = VenueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
