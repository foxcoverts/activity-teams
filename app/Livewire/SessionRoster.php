<?php

namespace App\Livewire;

use App\Enums\BookingAttendeeStatus;
use App\Livewire\Concerns\InteractsWithPersonAccess;
use App\Models\Booking;
use App\Models\User;
use App\Support\BookingGuestList;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SessionRoster extends Component
{
    use InteractsWithPersonAccess;

    public Booking $booking;

    public bool $showHeading = true;

    public function mount(Booking $booking): void
    {
        $actor = auth()->user();
        $booking->loadMissing('team');

        abort_unless(
            $actor instanceof User
                && $booking->team !== null
                && $actor->isCurrentMemberOf($booking->team),
            403,
        );

        $this->booking = $booking;
    }

    public function render(): View
    {
        $actor = auth()->user();
        $isLead = $actor instanceof User && $this->booking->isLead($actor);
        $isManager = $actor instanceof User
            && $this->booking->team !== null
            && $actor->manages($this->booking->team);
        $visibleStatuses = $isLead || $isManager
            ? BookingAttendeeStatus::cases()
            : [BookingAttendeeStatus::Accepted, BookingAttendeeStatus::Tentative];
        $attendees = $this->booking->attendees;

        if (! $isLead && ! $isManager && $actor instanceof User) {
            $attendees = $attendees->filter(
                fn (User $attendee): bool => in_array(
                    $attendee->attendance?->status,
                    [BookingAttendeeStatus::Accepted, BookingAttendeeStatus::Tentative],
                    true,
                ) || $attendee->is($actor),
            );

            $visibleStatuses = BookingAttendeeStatus::cases();
        }

        $roster = BookingGuestList::forBooking(
            $this->booking,
            $attendees,
            $visibleStatuses,
            includeSuspended: $isManager,
            includeAccessDetails: $isLead,
        );

        return view('filament.team.session-roster', [
            'groups' => $roster['groups'],
            'showQualified' => $isLead && $roster['show_qualified'],
            'showKeys' => $isLead && $roster['show_keys'],
            'showAccessDetails' => $isLead,
            'showHeading' => $this->showHeading,
            'panel' => 'member',
            'access' => $isLead ? $this->accessView() : null,
        ]);
    }

    protected function accessBooking(): Booking
    {
        return $this->booking;
    }

    protected function accessPersonAllowed(string $userId, string $panel): bool
    {
        $actor = auth()->user();

        if (! $actor instanceof User || ! $this->booking->isLead($actor)) {
            return false;
        }

        $roster = BookingGuestList::forBooking(
            $this->booking,
            $this->booking->attendees,
            BookingAttendeeStatus::cases(),
            includeSuspended: false,
            includeAccessDetails: true,
        );
        $person = collect($roster['groups'])
            ->flatMap(fn (array $group) => $group['people'])
            ->firstWhere('id', $userId);

        if (! is_array($person)) {
            return false;
        }

        return match ($panel) {
            'keys' => $person['key_badge'] === 'holder',
            'qualifications' => $person['qualified'],
            default => false,
        };
    }
}
