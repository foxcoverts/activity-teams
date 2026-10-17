<?php

namespace App\Livewire;

use App\Actions\RespondToBookingAction;
use App\Enums\BookingAttendeeStatus;
use App\Filament\Team\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\User;
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

    public function mount(Booking $booking, User $attendee): void
    {
        $this->booking = $booking->load(['activity', 'venue', 'attendees']);
        $this->attendee = $attendee;
        $this->invite = (string) request('invite');

        $attendance = $this->booking->attendees->find($attendee)?->attendance;
        abort_unless($attendance && $this->invite === $attendance->token, 403, __('Invitation invalid'));

        $this->response = $attendance->status;
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
    }

    public function bookingUrl(): string
    {
        return BookingResource::getUrl('view', ['record' => $this->booking], panel: 'team');
    }

    public function render(): View
    {
        return view('livewire.respond-to-booking')
            ->title(__('Respond to booking'));
    }
}
