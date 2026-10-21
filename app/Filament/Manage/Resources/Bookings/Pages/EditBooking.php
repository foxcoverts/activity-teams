<?php

namespace App\Filament\Manage\Resources\Bookings\Pages;

use App\Actions\UpdateBookingStatusAction;
use App\Filament\Manage\Resources\Bookings\BookingResource;
use App\Filament\Manage\Resources\Bookings\BookingStatusActions;
use App\Models\Booking;
use App\Models\User;
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
            BookingStatusActions::group(),
            DeleteAction::make(),
        ];
    }

    protected function authorizeAccess(): void
    {
        $record = $this->getRecord();

        if ($record instanceof Booking) {
            $this->record = $record->fresh() ?? $record;
        }

        parent::authorizeAccess();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return BookingResource::hydrateScheduleData($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return BookingResource::mergeScheduleData($data);
    }

    protected function afterSave(): void
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            return;
        }

        app(UpdateBookingStatusAction::class)(
            $this->record->refresh(),
            $actor,
        );
    }
}
