<?php

namespace App\Filament\Member\Widgets;

use App\Filament\Bookings\BookingRespondAction;
use App\Filament\Member\Resources\Bookings\BookingResource;
use App\Filament\Teams\Resources\Bookings\Schemas\BookingInfolist;
use App\Models\Booking;
use Filament\Actions\Action;

trait RespondsFromDashboard
{
    public function respondAction(): Action
    {
        return BookingRespondAction::make(BookingInfolist::components())
            ->record(fn (array $arguments): ?Booking => $this->bookingForResponse($arguments['booking'] ?? null))
            ->modalHeading(fn (Booking $record): string => $record->activityTitle())
            ->modalSubmitActionLabel(__('Save response'))
            ->extraModalFooterActions(fn (Booking $record): array => [
                Action::make('openBooking')
                    ->label(__('Open booking'))
                    ->color('gray')
                    ->url(BookingResource::viewUrl($record)),
            ]);
    }

    private function bookingForResponse(mixed $id): ?Booking
    {
        if (! is_string($id)) {
            return null;
        }

        return BookingResource::getEloquentQuery()->with('attendees')->find($id);
    }
}
