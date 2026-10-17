<?php

namespace App\Filament\Manage\Resources\Bookings\Pages;

use App\Actions\UpdateBookingStatusAction;
use App\Filament\Manage\Resources\Bookings\BookingResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditBooking extends EditRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        app(UpdateBookingStatusAction::class)(
            $this->record->refresh(),
            auth()->user(),
            $this->data['cancel_reason'] ?? null,
        );
    }
}
