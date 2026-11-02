<?php

namespace App\Jobs;

use App\Enums\BookingAttendeeStatus;
use App\Enums\MailChannel;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingChangedMail;
use App\Mail\BookingConfirmedMail;
use App\Mail\BookingInviteMail;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingHeldNotification;
use App\Support\BookingNotificationWindow;
use App\Support\MailPreference;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendBookingNotification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public Booking $booking,
        public string $token,
    ) {}

    public function handle(): void
    {
        $pending = BookingNotificationWindow::claim($this->booking, $this->token);

        if ($pending === null) {
            return;
        }

        $booking = $this->booking->fresh(['activity', 'venue', 'attendees']);

        if (! $booking) {
            return;
        }

        if ($booking->isCancelled()) {
            $this->sendStatus($booking, fn (User $attendee): BookingCancelledMail => new BookingCancelledMail(
                $booking,
                $attendee,
                $pending['cancel_reason'] ?? null,
            ), skipDeclined: true);

            return;
        }

        $kinds = $pending['kinds'] ?? [];

        if (in_array(BookingNotificationWindow::CONFIRMED, $kinds, true) && $booking->isConfirmed()) {
            $this->sendStatus($booking, fn (User $attendee): BookingConfirmedMail => new BookingConfirmedMail(
                $booking,
                $attendee,
                $pending['changes'] ?? [],
                $pending['previous_schedule'] ?? null,
            ));

            return;
        }

        if (in_array(BookingNotificationWindow::UPDATED, $kinds, true)) {
            $this->sendStatus($booking, fn (User $attendee): BookingChangedMail => new BookingChangedMail(
                $booking,
                $attendee,
                $pending['changes'] ?? [],
                $pending['previous_schedule'] ?? null,
            ), onlyUserIds: $pending['recipient_user_ids'] ?? null);

            return;
        }

        $this->sendInvites($booking, $pending['invite_user_ids'] ?? []);
    }

    /**
     * @param  callable(User): BookingChangedMail|BookingConfirmedMail|BookingCancelledMail  $message
     * @param  list<string>|null  $onlyUserIds
     */
    private function sendStatus(Booking $booking, callable $message, bool $skipDeclined = false, ?array $onlyUserIds = null): void
    {
        foreach ($booking->attendees as $attendee) {
            if ($onlyUserIds !== null && ! in_array((string) $attendee->getKey(), $onlyUserIds, true)) {
                continue;
            }

            if ($skipDeclined && $attendee->attendance?->status === BookingAttendeeStatus::Declined) {
                if (! $this->declinedViaImip($booking, $attendee)) {
                    continue;
                }
            }

            if ($attendee->isSuspended() || ! $attendee->hasVerifiedEmail()) {
                continue;
            }

            if (! MailPreference::allows($attendee, MailChannel::BookingsUpdates, $booking)) {
                $attendee->notify(new BookingHeldNotification(
                    $booking,
                    __('An update to :activity is in your inbox.', [
                        'activity' => $booking->activityTitle(),
                    ]),
                ));

                continue;
            }

            Mail::to($attendee)->send($message($attendee));
        }
    }

    /**
     * @param  array<int, string>  $userIds
     */
    private function sendInvites(Booking $booking, array $userIds): void
    {
        if (! $booking->acceptsInvites()) {
            return;
        }

        foreach (array_unique($userIds) as $userId) {
            $attendee = $booking->attendees->find($userId);

            if (! $attendee || $attendee->isSuspended() || ! $attendee->hasVerifiedEmail()) {
                continue;
            }

            if (! MailPreference::allows($attendee, MailChannel::BookingsMail, $booking)) {
                $attendee->notify(new BookingHeldNotification(
                    $booking,
                    __('You were invited to :activity.', [
                        'activity' => $booking->activityTitle(),
                    ]),
                ));

                continue;
            }

            Mail::to($attendee)->send(new BookingInviteMail($booking, $attendee));
        }
    }

    private function declinedViaImip(Booking $booking, User $attendee): bool
    {
        $activity = $booking->activitiesAsSubject()
            ->where('event', 'attendance_updated')
            ->where('properties->attendee_id', $attendee->getKey())
            ->where('properties->status', BookingAttendeeStatus::Declined->value)
            ->latest()
            ->first();

        return $activity?->getProperty('via') === 'imip';
    }
}
