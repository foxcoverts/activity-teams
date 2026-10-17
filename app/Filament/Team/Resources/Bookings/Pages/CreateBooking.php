<?php

namespace App\Filament\Team\Resources\Bookings\Pages;

use App\Filament\Team\Resources\Bookings\BookingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;
}
