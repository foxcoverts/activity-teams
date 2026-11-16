<?php

namespace App\Support;

use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use App\Enums\MailChannel;
use App\Enums\ScheduleNudgeTier;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BookingCatchUp
{
    /**
     * @return Collection<int, array{attendance: BookingAttendance, booking: Booking, tier: ScheduleNudgeTier, label: string}>
     */
    public function dueInvitationNudges(User $user): Collection
    {
        return $this->outstandingAttendances($user)
            ->map(function (BookingAttendance $attendance) use ($user): ?array {
                $booking = $attendance->booking;

                if (! $booking instanceof Booking) {
                    return null;
                }

                if (! MailPreference::allows($user, MailChannel::BookingsMail, $booking)) {
                    return null;
                }

                $tier = $this->dueInvitationTier($attendance, $booking);

                if (! $tier instanceof ScheduleNudgeTier) {
                    return null;
                }

                return [
                    'attendance' => $attendance,
                    'booking' => $booking,
                    'tier' => $tier,
                    'label' => $this->label($booking),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array{attendance: BookingAttendance, booking: Booking, label: string}>
     */
    public function dueSessionReminders(User $user): Collection
    {
        return BookingAttendance::query()
            ->where('user_id', $user->getKey())
            ->where('status', BookingAttendeeStatus::Accepted->value)
            ->whereNull('session_reminded_at')
            ->whereHas('booking', function ($query): void {
                $query->where('status', '!=', BookingStatus::Cancelled->value)
                    ->where('start_at', '>', now())
                    ->where('start_at', '<=', now()->addDays(7));
            })
            ->with(['booking.activity', 'booking.venue'])
            ->get()
            ->map(function (BookingAttendance $attendance) use ($user): ?array {
                $booking = $attendance->booking;

                if (! $booking instanceof Booking) {
                    return null;
                }

                if (! MailPreference::allows($user, MailChannel::BookingsReminders, $booking)) {
                    return null;
                }

                return [
                    'attendance' => $attendance,
                    'booking' => $booking,
                    'label' => $this->label($booking),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    public function candidateUserIds(): Collection
    {
        $nudgeUserIds = DB::table('booking_user')
            ->join('bookings', 'bookings.id', '=', 'booking_user.booking_id')
            ->whereIn('booking_user.status', array_map(
                fn (BookingAttendeeStatus $status): string => $status->value,
                BookingAttendeeStatus::outstanding(),
            ))
            ->where('bookings.status', '!=', BookingStatus::Cancelled->value)
            ->where('bookings.start_at', '>', now())
            ->where('bookings.start_at', '<=', now()->addDays(ScheduleNudgeTier::TwoMonths->days()))
            ->pluck('booking_user.user_id');

        $reminderUserIds = DB::table('booking_user')
            ->join('bookings', 'bookings.id', '=', 'booking_user.booking_id')
            ->where('booking_user.status', BookingAttendeeStatus::Accepted->value)
            ->whereNull('booking_user.session_reminded_at')
            ->where('bookings.status', '!=', BookingStatus::Cancelled->value)
            ->where('bookings.start_at', '>', now())
            ->where('bookings.start_at', '<=', now()->addDays(7))
            ->pluck('booking_user.user_id');

        return $nudgeUserIds->merge($reminderUserIds)->unique()->values();
    }

    public function markInvitationNudge(BookingAttendance $attendance, ScheduleNudgeTier $tier): void
    {
        // Do not touch updated_at — that column is the last invite/response clock for nudge age.
        BookingAttendance::query()
            ->where('booking_id', $attendance->booking_id)
            ->where('user_id', $attendance->user_id)
            ->update([
                'invitation_nudge_tier' => $tier->value,
            ]);

        $attendance->invitation_nudge_tier = $tier;
    }

    public function markSessionReminder(BookingAttendance $attendance): void
    {
        $remindedAt = now();

        BookingAttendance::query()
            ->where('booking_id', $attendance->booking_id)
            ->where('user_id', $attendance->user_id)
            ->update([
                'session_reminded_at' => $remindedAt,
            ]);

        $attendance->session_reminded_at = $remindedAt;
    }

    public function dueInvitationTier(BookingAttendance $attendance, Booking $booking): ?ScheduleNudgeTier
    {
        if ($booking->start_at === null) {
            return null;
        }

        // Use updated_at so a fresh Maybe (or other outstanding response) does not get an immediate nudge.
        $lastRespondedAt = $attendance->updated_at ?? $attendance->created_at;

        if ($lastRespondedAt === null) {
            return null;
        }

        return ScheduleNudgeTier::due(
            $attendance->invitation_nudge_tier,
            $booking->start_at,
            $lastRespondedAt,
        );
    }

    /**
     * @return Collection<int, BookingAttendance>
     */
    private function outstandingAttendances(User $user): Collection
    {
        return BookingAttendance::query()
            ->where('user_id', $user->getKey())
            ->whereIn('status', array_map(
                fn (BookingAttendeeStatus $status): string => $status->value,
                BookingAttendeeStatus::outstanding(),
            ))
            ->whereHas('booking', function ($query): void {
                $query->where('status', '!=', BookingStatus::Cancelled->value)
                    ->where('start_at', '>', now())
                    ->where('start_at', '<=', now()->addDays(ScheduleNudgeTier::TwoMonths->days()));
            })
            ->with(['booking.activity', 'booking.venue'])
            ->get();
    }

    private function label(Booking $booking): string
    {
        $booking->loadMissing('activity');

        return trim($booking->activityTitle().' '.$booking->start_at?->timezone(config('app.timezone'))->format('j M Y, H:i'));
    }
}
