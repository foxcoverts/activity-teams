<?php

namespace App\Livewire;

use App\Actions\RespondToBookingAction;
use App\Enums\BookingAttendeeStatus;
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

    public ?string $status = null;

    public function mount(Booking $booking, User $attendee): void
    {
        $this->booking = $booking->load(['activity', 'venue', 'attendees']);
        $this->attendee = $attendee;
        $this->invite = (string) request('invite');

        $attendance = $this->booking->attendees->find($attendee)?->attendance;
        abort_unless($attendance && $this->invite === $attendance->token, 403, __('Invitation invalid'));
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

        $this->status = $status;
    }

    public function render(): View
    {
        return view('livewire.respond-to-booking')
            ->title(__('Respond to booking'));
    }
}
