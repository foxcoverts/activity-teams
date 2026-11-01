<?php

namespace App\Actions;

use App\Enums\BookingAttendeeStatus;
use App\Enums\CalendarMethod;
use App\Enums\InboundMailOutcome;
use App\Models\Booking;
use App\Models\InboundMail;
use App\Models\Key;
use App\Models\Team;
use App\Models\User;
use App\Support\InboundCalendar;
use App\Support\InboundCalendarEvent;
use App\Support\InboundMailAddress;
use InvalidArgumentException;
use Throwable;

class ProcessInboundMailAction
{
    public function __invoke(InboundMail $mail): InboundMail
    {
        try {
            $calendar = $mail->calendar();
            $method = $calendar?->method();
            $resource = $this->resourceFromAddress($mail->to_address)
                ?? $calendar?->events()->map->booking()->filter()->first();

            if ($resource instanceof Booking || $resource instanceof Key || $resource instanceof Team) {
                $mail->resource()->associate($resource);
            }

            $mail->calendar_method = $method;

            if ($method === CalendarMethod::Reply && $calendar instanceof InboundCalendar) {
                return $this->handleReply($mail, $calendar);
            }

            if ($method === CalendarMethod::Refresh && $calendar instanceof InboundCalendar) {
                return $this->handleRefresh($mail, $calendar);
            }

            return $this->queueForReview($mail, __('This message could not be processed automatically.'));
        } catch (Throwable $e) {
            $mail->outcome = InboundMailOutcome::Failed;
            $mail->outcome_detail = $e->getMessage();
            $mail->save();

            return $mail;
        }
    }

    protected function handleReply(InboundMail $mail, InboundCalendar $calendar): InboundMail
    {
        $applied = 0;
        $resent = 0;
        $unmapped = 0;
        $booking = null;

        foreach ($calendar->events() as $event) {
            $booking = $event->booking();

            if (! $booking instanceof Booking) {
                continue;
            }

            $mail->resource()->associate($booking);

            try {
                $respond = new RespondToBookingAction($booking);
            } catch (InvalidArgumentException $e) {
                return $this->queueForReview($mail, $e->getMessage());
            }

            foreach ($event->attendees() as $attendee) {
                $user = $attendee->user();
                $status = $attendee->status();

                if (! $user instanceof User || $status === null || $status === BookingAttendeeStatus::NeedsAction) {
                    if ($user === null && filled($attendee->email())) {
                        $unmapped++;
                    }

                    continue;
                }

                if (! $booking->attendees()->whereKey($user->getKey())->exists()) {
                    $unmapped++;

                    continue;
                }

                // Ignore VEVENT COMMENT / X-RESPONSE-COMMENT this slice (climbing-team#142).
                $respond($user, $status, via: 'imip');
                $applied++;

                if ($this->isStale($booking, $event)) {
                    (new ResendBookingIcsAction)($booking, $user);
                    $resent++;
                }
            }
        }

        if ($applied > 0) {
            $mail->outcome = $resent > 0 ? InboundMailOutcome::Resent : InboundMailOutcome::Applied;
            $mail->outcome_detail = $resent > 0
                ? __('Recorded attendance and resent the current calendar invite.')
                : __('Recorded attendance from an iMIP reply.');
            $mail->handled_at = $mail->freshTimestamp();
            $mail->save();

            return $mail;
        }

        if ($unmapped > 0 || $booking === null) {
            return $this->queueForReview(
                $mail,
                $booking === null
                    ? __('No booking matched the calendar UID.')
                    : __('The reply came from an address that is not linked to a team member.'),
            );
        }

        return $this->queueForReview($mail, __('No attendance changes were applied.'));
    }

    protected function handleRefresh(InboundMail $mail, InboundCalendar $calendar): InboundMail
    {
        $resent = 0;

        foreach ($calendar->events() as $event) {
            $booking = $event->booking();

            if (! $booking instanceof Booking) {
                continue;
            }

            $mail->resource()->associate($booking);

            foreach ($event->attendees() as $attendee) {
                $user = $attendee->user();

                if (! $user instanceof User) {
                    continue;
                }

                if (! $booking->attendees()->whereKey($user->getKey())->exists()) {
                    continue;
                }

                (new ResendBookingIcsAction)($booking, $user);
                $resent++;
            }

            if ($resent === 0 && filled($mail->from_address)) {
                $user = User::query()->where('email', $mail->from_address)->first();

                if ($user instanceof User && $booking->attendees()->whereKey($user->getKey())->exists()) {
                    (new ResendBookingIcsAction)($booking, $user);
                    $resent++;
                }
            }
        }

        if ($resent > 0) {
            $mail->outcome = InboundMailOutcome::Resent;
            $mail->outcome_detail = __('Resent the current calendar invite.');
            $mail->handled_at = $mail->freshTimestamp();
            $mail->save();

            return $mail;
        }

        return $this->queueForReview($mail, __('No attendee matched this refresh request.'));
    }

    protected function queueForReview(InboundMail $mail, string $detail): InboundMail
    {
        $mail->outcome = InboundMailOutcome::Queued;
        $mail->outcome_detail = $detail;
        $mail->save();

        return $mail;
    }

    protected function resourceFromAddress(?string $address): Booking|Key|Team|null
    {
        if (! filled($address)) {
            return null;
        }

        return InboundMailAddress::findResource($address);
    }

    protected function isStale(Booking $booking, InboundCalendarEvent $event): bool
    {
        $sequence = $event->sequence();

        if ($sequence !== null) {
            return $sequence < (int) $booking->sequence;
        }

        return false;
    }
}
