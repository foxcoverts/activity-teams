<?php

namespace App\Filament\Member\Widgets;

use App\Enums\BookingAttendeeResponse;
use App\Filament\Bookings\BookingResponseChoices;
use App\Filament\Bookings\DashboardRespondActions;
use App\Filament\Member\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;

trait RespondsFromDashboard
{
    public function respondYesAction(): Action
    {
        return $this->dashboardResponseAction(BookingAttendeeResponse::Yes);
    }

    public function respondMaybeAction(): Action
    {
        return $this->dashboardResponseAction(BookingAttendeeResponse::Maybe);
    }

    public function respondNoAction(): Action
    {
        return $this->dashboardResponseAction(BookingAttendeeResponse::No);
    }

    public function respondGroupFor(Booking $booking): ActionGroup
    {
        return DashboardRespondActions::make($booking, $this);
    }

    private function dashboardResponseAction(BookingAttendeeResponse $response): Action
    {
        return BookingResponseChoices::action($response)
            ->record(fn (array $arguments): ?Booking => $this->bookingForResponse($arguments['booking'] ?? null));
    }

    private function bookingForResponse(mixed $id): ?Booking
    {
        if (! is_string($id)) {
            return null;
        }

        return BookingResource::getEloquentQuery()->with('attendees')->find($id);
    }
}
