<?php

namespace App\Support;

use App\Enums\BookingAttendeeStatus;
use App\Enums\MailChannel;
use App\Mail\BookingLogbookNudgeMail;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

final class LogbookCatchUp
{
    public function __construct(private Logbook $logbook) {}

    /**
     * @return Collection<int, array{booking: Booking, attendance: BookingAttendance, label: string}>
     */
    public function dueFocusedNudges(User $user): Collection
    {
        if (! MailPreference::allows($user, MailChannel::BookingsLogbook)) {
            return collect();
        }

        return $this->outstandingUnnudged($user)
            ->map(function (Booking $booking): ?array {
                $attendance = $booking->attendees->first()?->attendance;

                if (! $attendance instanceof BookingAttendance) {
                    return null;
                }

                return [
                    'booking' => $booking,
                    'attendance' => $attendance,
                    'label' => $booking->activityTitle(),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array{booking: Booking, label: string}>
     */
    public function outstandingSummary(User $user): Collection
    {
        if (! MailPreference::allows($user, MailChannel::BookingsLogbook)) {
            return collect();
        }

        return $this->logbook->outstanding($user)
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
        return BookingAttendance::query()
            ->where('status', BookingAttendeeStatus::Accepted->value)
            ->whereNull('logbook_ignored_at')
            ->whereNull('logbook_nudged_at')
            ->whereHas('booking', fn ($query) => $query->whereNotNull('end_at'))
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('logbook_entries')
                    ->whereColumn('logbook_entries.user_id', 'booking_user.user_id')
                    ->whereColumn('logbook_entries.booking_id', 'booking_user.booking_id');
            })
            ->pluck('user_id')
            ->unique()
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, Booking>
     */
    public function outstandingUnnudged(User $user): Collection
    {
        return $this->logbook->outstandingQuery($user)
            ->whereHas(
                'attendees',
                fn ($query) => $query
                    ->where('users.id', $user->getKey())
                    ->whereNull('booking_user.logbook_nudged_at'),
            )
            ->with([
                'attendees' => fn ($attendees) => $attendees->where('users.id', $user->getKey()),
            ])
            ->get()
            ->filter(fn (Booking $booking): bool => $this->logbook->sessionDayIsDue($booking))
            ->values();
    }

    public function sendFocused(User $user, Booking $booking, BookingAttendance $attendance): void
    {
        Mail::to($user)->send(new BookingLogbookNudgeMail($booking, $user));
        $this->mark($attendance);
    }

    public function mark(BookingAttendance $attendance): void
    {
        $attendance->forceFill(['logbook_nudged_at' => now()])->save();
    }
}
