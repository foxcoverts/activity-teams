<?php

namespace App\Filament\Teams\Resources\Bookings\Actions;

use App\Actions\SaveBookingDetailsAction;
use App\Filament\Teams\Resources\Bookings\Schemas\BookingForm;
use App\Models\Booking;
use App\Models\Team;
use Filament\Actions\CreateAction as BaseCreateAction;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;

class CreateAction
{
    public static function make(): BaseCreateAction
    {
        return BaseCreateAction::make()
            ->authorizationTooltip()
            ->using(function (array $data): Booking {
                $team = Filament::getTenant();

                if (! $team instanceof Team) {
                    throw ValidationException::withMessages([
                        'team_id' => __('Choose a team.'),
                    ]);
                }

                return app(SaveBookingDetailsAction::class)->create(
                    BookingForm::mergeScheduleData($data),
                    $team,
                );
            });
    }
}
