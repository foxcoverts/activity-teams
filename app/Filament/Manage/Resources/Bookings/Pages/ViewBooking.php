<?php

namespace App\Filament\Manage\Resources\Bookings\Pages;

use App\Filament\Manage\Resources\Bookings\BookingResource;
use App\Filament\Manage\Resources\Bookings\BookingStatusActions;
use App\Models\Booking;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        if ($record instanceof Booking && $record->isCancelled()) {
            return [
                BookingStatusActions::restore()->button(),
                DeleteAction::make(),
            ];
        }

        return [
            EditAction::make(),
            BookingStatusActions::group(),
            DeleteAction::make(),
        ];
    }
}
