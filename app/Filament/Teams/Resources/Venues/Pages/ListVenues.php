<?php

namespace App\Filament\Teams\Resources\Venues\Pages;

use App\Filament\Teams\Resources\Venues\Actions\GiveVenueAction;
use App\Filament\Teams\Resources\Venues\VenueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVenues extends ListRecords
{
    protected static string $resource = VenueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            GiveVenueAction::make(),
        ];
    }
}
