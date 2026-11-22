<?php

namespace App\Filament\Teams\Resources\Bookings;

use App\Enums\TimelineAudience;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use App\Support\Palette;
use App\Support\TimelineView;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Component as LivewireComponent;
use RalphJSmit\Filament\Activitylog\Filament\Infolists\Components\Timeline;
use Spatie\Activitylog\Models\Activity as ActivityLog;

class BookingTimeline
{
    public static function section(
        TimelineAudience $audience,
        bool $showAudienceToggle = false,
        ?LivewireComponent $livewire = null,
        ?Booking $booking = null,
        ?User $viewer = null,
    ): Section {
        TimelineView::set($audience, $viewer, $booking);

        $section = Section::make(__('Recent activity'))
            ->key('booking-recent-activity-'.$audience->value)
            ->columnSpanFull()
            ->components([
                static::entry($audience)->hiddenLabel(),
            ]);

        if ($showAudienceToggle && $livewire !== null && method_exists($livewire, 'toggleTimelineAudience')) {
            $section->headerActions([
                Action::make('viewTimelineAsMember')
                    ->label(fn (): string => $livewire->timelineAsMember
                        ? __('Viewing as member')
                        : __('View as member'))
                    ->icon(Heroicon::OutlinedEye)
                    ->link()
                    ->color(fn (): string|array => $livewire->timelineAsMember
                        ? Palette::uncertain()
                        : Palette::neutral())
                    ->action(fn () => $livewire->toggleTimelineAudience()),
            ]);
        }

        return $section;
    }

    public static function entry(TimelineAudience $audience): Timeline
    {
        $timeline = Timeline::make()
            ->columnSpanFull()
            ->causerUrl(fn (?Model $causer, ActivityLog $activity): ?string => $audience->isManager()
                ? static::memberUrl($causer, $activity)
                : null)
            ->recordUrl(Booking::class, fn (Booking $booking): ?string => $audience->isManager()
                ? static::bookingUrl($booking)
                : null)
            ->recordUrl(User::class, fn (User $user, ActivityLog $activity): ?string => $audience->isManager()
                ? static::memberUrl($user, $activity)
                : null)
            ->recordUrl(Activity::class, fn (): null => null)
            ->recordUrl(Venue::class, fn (): null => null)
            ->recordUrl(Team::class, fn (): null => null);

        if ($audience->isMember()) {
            $timeline->modifyActivitiesQueryUsing(function (Builder $query): Builder {
                return $query->where(function (Builder $events): void {
                    $events->whereNull('event')
                        ->orWhereNotIn('event', ['attendees_invited', 'attendee_removed']);
                });
            });

            $timeline->modifyActivitiesUsing(function (Collection $activities): Collection {
                $hidden = ['lead_needed_at'];

                if (! TimelineView::viewerIsLead()) {
                    $hidden[] = 'lead_instructor_notes';
                }

                return $activities
                    ->reject(function (ActivityLog $activity) use ($hidden): bool {
                        if ($activity->event !== 'updated' || $activity->description !== $activity->event) {
                            return false;
                        }

                        $keys = collect($activity->attribute_changes?->get('attributes', []) ?? [])->keys();

                        if ($keys->isEmpty()) {
                            return false;
                        }

                        $ignored = ['created_at', 'updated_at', 'deleted_at'];
                        $visible = $keys->reject(
                            fn (string $key): bool => in_array($key, $hidden, true) || in_array($key, $ignored, true)
                        );

                        return $visible->isEmpty();
                    })
                    ->values();
            });
        }

        return $timeline;
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

        return BookingResource::getUrl('view', ['record' => $booking], panel: 'teams', tenant: $team);
    }

    protected static function memberUrl(?Model $causer, ActivityLog $activity): ?string
    {
        if (! $causer instanceof User) {
            return null;
        }

        return MemberResource::personUrlFor($causer, static::teamFor($activity));
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
