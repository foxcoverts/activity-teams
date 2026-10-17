<?php

namespace App\Jobs;

use App\Mail\BookingCancelledMail;
use App\Mail\BookingChangedMail;
use App\Mail\BookingConfirmedMail;
use App\Mail\BookingInviteMail;
use App\Models\Booking;
use App\Models\User;
use App\Support\BookingNotificationWindow;
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
            ));

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
            ));

            return;
        }

        $this->sendInvites($booking, $pending['invite_user_ids'] ?? []);
    }

    /**
     * @param  callable(User): BookingChangedMail|BookingConfirmedMail|BookingCancelledMail  $message
     */
    private function sendStatus(Booking $booking, callable $message): void
    {
        foreach ($booking->attendees as $attendee) {
            if ($attendee->isSuspended() || ! $attendee->hasVerifiedEmail()) {
                continue;
            }

            Mail::to($attendee->email)->send($message($attendee));
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

            Mail::to($attendee->email)->send(new BookingInviteMail($booking, $attendee));
        }
    }
}
