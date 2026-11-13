<?php

namespace App\Support;

use App\Enums\BookingStatus;
use App\Enums\TeamFeature;
use App\Mail\BookingDebriefNudgeMail;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

final class DebriefCatchUp
{
    /**
     * @return Collection<int, array{booking: Booking, label: string, week: bool}>
     */
    public function dueFocusedNudges(User $user): Collection
    {
        return $this->outstandingLedBy($user)
            ->map(function (Booking $booking): ?array {
                $end = $booking->end_at;

                if ($end === null) {
                    return null;
                }

                $initialDue = $end->copy()->addHour()->isPast();
                $weekDue = $end->copy()->addWeek()->isPast();

                if ($weekDue && $booking->debrief_week_nudged_at === null) {
                    return [
                        'booking' => $booking,
                        'label' => $booking->activityTitle(),
                        'week' => true,
                    ];
                }

                if ($initialDue && $booking->debrief_nudged_at === null) {
                    return [
                        'booking' => $booking,
                        'label' => $booking->activityTitle(),
                        'week' => false,
                    ];
                }

                return null;
            })
            ->filter()
            ->values();
    }

    /**
     * Outstanding incomplete debriefs for digest summaries (does not enrol sendAll alone).
     *
     * @return Collection<int, array{booking: Booking, label: string}>
     */
    public function outstandingSummary(User $user): Collection
    {
        return $this->outstandingLedBy($user)
            ->filter(fn (Booking $booking): bool => $booking->end_at?->copy()->addHour()->isPast() ?? false)
            ->map(fn (Booking $booking): array => [
                'booking' => $booking,
                'label' => $booking->activityTitle(),
            ])
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    public function candidateUserIds(): Collection
    {
        return Booking::query()
            ->whereNotNull('lead_instructor_id')
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Tentative->value])
            ->whereDoesntHave('debrief')
            ->whereHas(
                'team',
                fn ($query) => $query->whereFeatureIsEnabled(TeamFeature::Debrief),
            )
            ->where(function ($query): void {
                $query
                    ->where(function ($initial): void {
                        $initial
                            ->whereNull('debrief_nudged_at')
                            ->where('end_at', '<=', now()->subHour());
                    })
                    ->orWhere(function ($week): void {
                        $week
                            ->whereNull('debrief_week_nudged_at')
                            ->where('end_at', '<=', now()->subWeek());
                    });
            })
            ->pluck('lead_instructor_id')
            ->unique()
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, Booking>
     */
    public function outstandingLedBy(User $user): Collection
    {
        return Booking::query()
            ->with([
                'team',
                'activity',
                'audience',
                'venue',
                'debrief',
                'attendees' => fn ($attendees) => $attendees->where('users.id', $user->getKey()),
            ])
            ->where('lead_instructor_id', $user->getKey())
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Tentative->value])
            ->whereDoesntHave('debrief')
            ->whereHas(
                'team',
                fn ($query) => $query->whereFeatureIsEnabled(TeamFeature::Debrief),
            )
            ->orderBy('end_at')
            ->get()
            ->filter(fn (Booking $booking): bool => $booking->needsDebrief());
    }

    public function sendFocused(User $user, Booking $booking, bool $week): void
    {
        Mail::to($user)->send(new BookingDebriefNudgeMail($booking, $user, week: $week));
        $this->mark($booking, $week);
    }

    public function mark(Booking $booking, bool $week): void
    {
        if ($week) {
            $booking->forceFill([
                'debrief_week_nudged_at' => now(),
                'debrief_nudged_at' => $booking->debrief_nudged_at ?? now(),
            ])->save();

            return;
        }

        $booking->forceFill(['debrief_nudged_at' => now()])->save();
    }
}
