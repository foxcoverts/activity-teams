<?php

namespace App\Filament\Admin\Resources\Venues\Pages;

use App\Filament\Admin\Resources\Venues\VenueResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVenue extends CreateRecord
{
    protected static string $resource = VenueResource::class;
}
