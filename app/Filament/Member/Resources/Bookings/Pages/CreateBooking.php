<?php

namespace App\Filament\Member\Resources\Bookings\Pages;

use App\Filament\Member\Resources\Bookings\BookingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;
}
