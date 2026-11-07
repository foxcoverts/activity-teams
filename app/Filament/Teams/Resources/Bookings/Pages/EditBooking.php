<?php

namespace App\Filament\Teams\Resources\Bookings\Pages;

use App\Actions\SaveBookingDetailsAction;
use App\Actions\UpdateBookingStatusAction;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Filament\Teams\Resources\Bookings\BookingStatusActions;
use App\Filament\Teams\Resources\Bookings\Schemas\BookingForm;
use App\Models\Booking;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditBooking extends EditRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            BookingStatusActions::group(includeEdit: false),
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
        $record = $this->getRecord();

        return $record instanceof Booking
            ? BookingForm::hydrateBookingFormData($data, $record)
            : BookingForm::hydrateScheduleData($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return BookingForm::mergeScheduleData($data);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless($record instanceof Booking, 404);

        return app(SaveBookingDetailsAction::class)->update($record, $data);
    }

    protected function afterSave(): void
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            return;
        }

        app(UpdateBookingStatusAction::class)(
            $this->record,
            $actor,
        );
    }
}
