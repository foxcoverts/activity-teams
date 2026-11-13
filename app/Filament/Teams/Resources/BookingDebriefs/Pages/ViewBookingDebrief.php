<?php

namespace App\Filament\Teams\Resources\BookingDebriefs\Pages;

use App\Filament\Teams\Resources\BookingDebriefs\BookingDebriefResource;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\BookingDebrief;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewBookingDebrief extends ViewRecord
{
    protected static string $resource = BookingDebriefResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openBooking')
                ->label(__('Open booking'))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->url(function (): ?string {
                    $record = $this->getRecord();

                    if (! $record instanceof BookingDebrief) {
                        return null;
                    }

                    $record->loadMissing('booking.team');
                    $booking = $record->booking;

                    if (! $booking instanceof Booking || $booking->team === null) {
                        return null;
                    }

                    return BookingResource::getUrl(
                        'view',
                        ['record' => $booking],
                        panel: 'teams',
                        tenant: $booking->team,
                    );
                }),
        ];
    }
}
