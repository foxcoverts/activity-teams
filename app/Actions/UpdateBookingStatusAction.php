<?php

namespace App\Actions;

use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use App\Events\BookingCancelled;
use App\Events\BookingChanged;
use App\Events\BookingConfirmed;
use App\Events\BookingRestored;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class UpdateBookingStatusAction
{
    public function __construct(private SaveBookingDetailsAction $saveBookingDetails) {}

    public function __invoke(Booking $booking, User $actor, ?string $cancelReason = null): void
    {
        $previousStatus = $this->previousStatus($booking);

        if ($previousStatus === BookingStatus::Cancelled && ! $booking->isCancelled()) {
            $changes = $booking->getChanges();
            $previousSchedule = $this->previousSchedule($booking);
            $this->resetAttendanceExceptDeclined($booking);
            $booking->refresh();
            $booking->load('attendees');

            event(new BookingRestored($booking, $actor, $changes, $previousSchedule));

            return;
        }

        if ($previousStatus !== $booking->status && $booking->isConfirmed()) {
            event(new BookingConfirmed($booking, $actor, $booking->getChanges(), $this->previousSchedule($booking)));

            return;
        }

        if ($previousStatus !== $booking->status && $booking->isCancelled()) {
            event(new BookingCancelled($booking, $actor, $cancelReason ?? $booking->cancellationReason));

            return;
        }

        if (
            ! $booking->isCancelled()
            && $previousStatus === $booking->status
            && $booking->wasChanged([
                'sequence',
                'group_name',
                'notes',
                'lead_instructor_id',
                'timezone',
            ])
        ) {
            event(new BookingChanged($booking, $actor, $booking->getChanges(), $this->previousSchedule($booking)));
        }
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public function confirm(Booking $booking, User $actor, ?string $leadId, array $details): void
    {
        if (! $booking->isTentative()) {
            throw ValidationException::withMessages([
                'status' => __('Only a tentative booking can be confirmed.'),
            ]);
        }

        $booking->fill(Arr::except($details, ['private_venue']));

        $requiresLead = (bool) ($details['requires_lead_instructor'] ?? $booking->requires_lead_instructor);

        if ($requiresLead) {
            if (blank($leadId)) {
                throw ValidationException::withMessages([
                    'lead_instructor_id' => __('A lead instructor must be attending.'),
                ]);
            }

            $booking->lead_instructor_id = $leadId;
            $booking->requires_lead_instructor = true;
            $this->assertAttending($booking, $leadId);
            $details['lead_instructor_id'] = $leadId;
        } else {
            $booking->lead_instructor_id = null;
            $booking->requires_lead_instructor = false;
            $details['lead_instructor_id'] = null;
            $details['requires_lead_instructor'] = false;
        }

        $booking->status = BookingStatus::Confirmed;
        $details['status'] = BookingStatus::Confirmed;
        $this->saveBookingDetails->update($booking, $details);

        $this($booking, $actor);
    }

    public function cancel(Booking $booking, User $actor, string $reason): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => __('A reason is required.'),
            ]);
        }

        if (! $booking->isTentative() && ! $booking->isConfirmed()) {
            throw ValidationException::withMessages([
                'status' => __('This booking cannot be cancelled.'),
            ]);
        }

        $booking->cancellationReason = $reason;
        $booking->status = BookingStatus::Cancelled;
        $booking->save();

        $this($booking, $actor, $reason);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public function restore(Booking $booking, User $actor, array $details = []): void
    {
        if (! $booking->isCancelled()) {
            throw ValidationException::withMessages([
                'status' => __('Only a cancelled booking can be restored.'),
            ]);
        }

        unset($details['status'], $details['lead_instructor_id']);

        $booking->fill(Arr::except($details, ['private_venue']));
        $booking->status = BookingStatus::Tentative;

        if ($booking->leadInstructor?->isSuspended()) {
            $booking->lead_instructor_id = null;
        }

        $details['status'] = BookingStatus::Tentative;
        $details['lead_instructor_id'] = $booking->lead_instructor_id;
        $this->saveBookingDetails->update($booking, $details);

        $this($booking, $actor);
    }

    public function returnToTentative(Booking $booking, User $actor): void
    {
        if (! $booking->isConfirmed()) {
            throw ValidationException::withMessages([
                'status' => __('Only a confirmed booking can return to tentative.'),
            ]);
        }

        $booking->status = BookingStatus::Tentative;
        $booking->save();
    }

    public function changeLead(Booking $booking, User $actor, ?string $leadId): void
    {
        if (! $booking->isTentative() && ! $booking->isConfirmed()) {
            throw ValidationException::withMessages([
                'status' => __('The lead instructor can only be changed on a tentative or confirmed booking.'),
            ]);
        }

        if ($leadId === null) {
            $cleared = (new ClearLeadInstructorAction)($booking, $actor, attendeesWillBeUpdated: true);

            if ($cleared) {
                event(new BookingChanged($booking, $actor, $booking->getChanges(), $this->previousSchedule($booking)));
            }

            return;
        }

        $this->assertAttending($booking, $leadId);

        $booking->lead_instructor_id = $leadId;
        $booking->save();

        $this($booking, $actor);
    }

    public function assertAttending(Booking $booking, string $leadId): void
    {
        $booking->loadMissing('attendees');
        $lead = $booking->attendees->find($leadId);

        if ($lead === null || $lead->attendance?->status !== BookingAttendeeStatus::Accepted) {
            throw ValidationException::withMessages([
                'lead_instructor_id' => __('A lead instructor must be attending.'),
            ]);
        }

        if (! $booking->metBy($lead)) {
            throw ValidationException::withMessages([
                'lead_instructor_id' => __('The lead instructor must be qualified for this activity.'),
            ]);
        }
    }

    private function resetAttendanceExceptDeclined(Booking $booking): void
    {
        $booking->load('attendees');

        $rows = [];

        foreach ($booking->attendees as $attendee) {
            $attendance = $attendee->attendance;
            $status = $attendance->status === BookingAttendeeStatus::Declined
                ? BookingAttendeeStatus::Declined
                : BookingAttendeeStatus::NeedsAction;

            $rows[$attendee->getKey()] = [
                'status' => $status->value,
                'comment' => $attendance->comment,
                'token' => $attendance->token,
            ];
        }

        $booking->attendees()->sync($rows);
    }

    private function previousStatus(Booking $booking): ?BookingStatus
    {
        $previous = $booking->getPrevious()['status'] ?? $booking->getOriginal('status');

        if ($previous instanceof BookingStatus) {
            return $previous;
        }

        return BookingStatus::tryFrom((string) $previous);
    }

    /**
     * @return array{start_at: mixed, end_at: mixed, timezone: mixed}|null
     */
    private function previousSchedule(Booking $booking): ?array
    {
        $changed = array_intersect(
            ['start_at', 'end_at', 'timezone'],
            array_keys($booking->getChanges()),
        );

        if ($changed === []) {
            return null;
        }

        $previous = $booking->getPrevious();

        return [
            'start_at' => $previous['start_at'] ?? $booking->getRawOriginal('start_at'),
            'end_at' => $previous['end_at'] ?? $booking->getRawOriginal('end_at'),
            'timezone' => $previous['timezone'] ?? $booking->getRawOriginal('timezone'),
        ];
    }
}
