<?php

namespace App\Filament\Manage\Resources\Bookings;

use App\Filament\Manage\Resources\Members\MemberResource;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;
use RalphJSmit\Filament\Activitylog\Filament\Infolists\Components\Timeline;
use Spatie\Activitylog\Models\Activity as ActivityLog;

class BookingTimeline
{
    public static function section(): Section
    {
        return Section::make(__('Timeline'))
            ->columnSpanFull()
            ->components([
                static::entry()->hiddenLabel(),
            ]);
    }

    public static function entry(): Timeline
    {
        return Timeline::make()
            ->columnSpanFull()
            ->causerUrl(fn (?Model $causer, ActivityLog $activity): ?string => static::memberUrl($causer, $activity))
            ->recordUrl(Booking::class, fn (Booking $booking): ?string => static::bookingUrl($booking))
            ->recordUrl(User::class, fn (User $user, ActivityLog $activity): ?string => static::memberUrl($user, $activity))
            ->recordUrl(Activity::class, fn (): null => null)
            ->recordUrl(Venue::class, fn (): null => null)
            ->recordUrl(Team::class, fn (): null => null);
    }

    protected static function bookingUrl(Booking $booking): ?string
    {
        if (! BookingResource::canView($booking)) {
            return null;
        }

        $team = static::teamOf($booking);

        if (! $team instanceof Team) {
            return null;
        }

        return BookingResource::getUrl('view', ['record' => $booking], panel: 'manage', tenant: $team);
    }

    protected static function memberUrl(?Model $causer, ActivityLog $activity): ?string
    {
        if (! $causer instanceof User) {
            return null;
        }

        $team = static::teamFor($activity);

        if (! $team instanceof Team || ! $causer->teams()->whereKey($team->getKey())->exists()) {
            return null;
        }

        if (! MemberResource::canView($causer)) {
            return null;
        }

        return MemberResource::getUrl('view', ['record' => $causer], panel: 'manage', tenant: $team);
    }

    protected static function teamFor(ActivityLog $activity): ?Team
    {
        $subject = $activity->relationLoaded('subject') ? $activity->subject : null;

        if (! $subject instanceof Booking && $activity->subject_type === Booking::class) {
            $subject = Booking::query()->find($activity->subject_id);
        }

        if ($subject instanceof Booking) {
            return static::teamOf($subject);
        }

        $tenant = Filament::getTenant();

        return $tenant instanceof Team ? $tenant : null;
    }

    protected static function teamOf(Booking $booking): ?Team
    {
        if ($booking->relationLoaded('team')) {
            return $booking->team;
        }

        return Team::query()->find($booking->team_id);
    }
}
