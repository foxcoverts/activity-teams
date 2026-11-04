<?php

namespace App\Filament\Teams\Resources\Bookings;

use App\Models\Activity;
use App\Models\Booking;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use Filament\Schemas\Components\Section;
use RalphJSmit\Filament\Activitylog\Filament\Infolists\Components\Timeline;

class BookingTimeline
{
    public static function section(): Section
    {
        return Section::make(__('Recent activity'))
            ->columnSpanFull()
            ->components([
                static::entry()->hiddenLabel(),
            ]);
    }

    public static function entry(): Timeline
    {
        return Timeline::make()
            ->columnSpanFull()
            ->causerUrl(fn (): null => null)
            ->recordUrl(Booking::class, fn (Booking $booking): ?string => static::bookingUrl($booking))
            ->recordUrl(Activity::class, fn (): null => null)
            ->recordUrl(Venue::class, fn (): null => null)
            ->recordUrl(User::class, fn (): null => null)
            ->recordUrl(Team::class, fn (): null => null);
    }

    protected static function bookingUrl(Booking $booking): ?string
    {
        if (! BookingResource::canView($booking)) {
            return null;
        }

        return BookingResource::getUrl('view', ['record' => $booking], panel: 'teams', tenant: $booking->team);
    }
}
