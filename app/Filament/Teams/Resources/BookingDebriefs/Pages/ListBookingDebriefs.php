<?php

namespace App\Filament\Teams\Resources\BookingDebriefs\Pages;

use App\Filament\Teams\Resources\BookingDebriefs\BookingDebriefResource;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use Filament\Resources\Pages\ListRecords;

class ListBookingDebriefs extends ListRecords
{
    protected static string $resource = BookingDebriefResource::class;

    /**
     * @return array<string, string|null>
     */
    public function getBreadcrumbs(): array
    {
        return [
            BookingResource::getUrl() => BookingResource::getNavigationLabel() ?? __('Bookings'),
            ...parent::getBreadcrumbs(),
        ];
    }
}
