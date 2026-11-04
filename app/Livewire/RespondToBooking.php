<?php

namespace App\Livewire;

use App\Actions\RespondToBookingAction;
use App\Enums\BookingAttendeeStatus;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\User;
use App\Support\LeadAttendanceWarning;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Component;

class RespondToBooking extends Component
{
    public Booking $booking;

    public User $attendee;

    public string $invite = '';

    public BookingAttendeeStatus $response;

    public bool $changing = false;

    public ?string $pendingChoice = null;

    public bool $sequenceStale = false;

    public function mount(Booking $booking, User $attendee): void
    {
        $this->booking = $booking->load(['activity', 'venue', 'attendees']);
        $this->attendee = $attendee;
        $this->invite = (string) request('invite');

        $attendance = $this->booking->attendees->find($attendee)?->attendance;
        abort_unless($attendance && $this->invite === $attendance->token, 403, __('Invitation invalid'));

        $this->response = $attendance->status;
        $this->sequenceStale = $this->linkSequenceIsStale();
        $this->pendingChoice = $this->requestedChoice();
    }

    public function cancelChoice(): void
    {
        $this->pendingChoice = null;
    }

    public function changeResponse(): void
    {
        if (! $this->booking->acceptsInvites()) {
            return;
        }

        $this->changing = true;
    }

    public function keepResponse(): void
    {
        $this->changing = false;
    }

    public function respond(string $status): void
    {
        try {
            (new RespondToBookingAction($this->booking))(
                $this->attendee,
                BookingAttendeeStatus::from($status),
            );
        } catch (InvalidArgumentException $e) {
            $this->addError('status', $e->getMessage());

            return;
        }

        $this->booking->unsetRelation('attendees');
        $this->booking->load('attendees');
        $this->response = $this->booking->attendees->find($this->attendee)->attendance->status;
        $this->changing = false;
        $this->pendingChoice = null;
        $this->sequenceStale = false;
    }

    public function leadWarning(): ?string
    {
        if (! $this->booking->isLead($this->attendee) || ! $this->booking->acceptsInvites()) {
            return null;
        }

        $aboutToAnswer = $this->pendingChoice !== null
            || $this->changing
            || $this->response === BookingAttendeeStatus::NeedsAction;

        if (! $aboutToAnswer) {
            return null;
        }

        return LeadAttendanceWarning::message(
            $this->booking,
            BookingAttendeeStatus::tryFrom((string) $this->pendingChoice),
        );
    }

    public function pendingChoiceLabel(): ?string
    {
        $choice = BookingAttendeeStatus::tryFrom((string) $this->pendingChoice);

        return match ($choice) {
            BookingAttendeeStatus::Accepted => __('Yes, I can help'),
            BookingAttendeeStatus::Tentative => __('Maybe'),
            BookingAttendeeStatus::Declined => __('I cannot make it'),
            default => null,
        };
    }

    private function requestedChoice(): ?string
    {
        $choice = request('choice');

        if (! is_string($choice) || ! $this->booking->acceptsInvites()) {
            return null;
        }

        $status = BookingAttendeeStatus::tryFrom($choice);

        if ($status === null || $status === BookingAttendeeStatus::NeedsAction || $status === $this->response) {
            return null;
        }

        return $status->value;
    }

    private function linkSequenceIsStale(): bool
    {
        $sequence = request('sequence');

        if ($sequence === null || $sequence === '') {
            return false;
        }

        if (! is_numeric($sequence)) {
            return true;
        }

        return (int) $sequence < (int) $this->booking->sequence;
    }

    public function bookingUrl(): string
    {
        return BookingResource::getUrl('view', ['record' => $this->booking], panel: 'teams', tenant: $this->booking->team);
    }

    public function render(): View
    {
        return view('livewire.respond-to-booking')
            ->title(__('Respond to booking'));
    }
}
