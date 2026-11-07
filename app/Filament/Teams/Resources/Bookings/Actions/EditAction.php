<?php

namespace App\Filament\Teams\Resources\Bookings\Actions;

use App\Actions\SaveBookingDetailsAction;
use App\Filament\Teams\Resources\Bookings\Schemas\BookingForm;
use App\Models\Booking;
use Filament\Actions\EditAction as BaseEditAction;

class EditAction
{
    public static function make(): BaseEditAction
    {
        return BaseEditAction::make()
            ->mutateRecordDataUsing(
                fn (array $data, Booking $record): array => BookingForm::hydrateBookingFormData($data, $record),
            )
            ->using(
                fn (array $data, Booking $record): Booking => app(SaveBookingDetailsAction::class)->update(
                    $record,
                    BookingForm::mergeScheduleData($data),
                ),
            );
    }
}
