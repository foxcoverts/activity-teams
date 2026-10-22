<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithPersonAccess;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\User;
use App\Support\BookingPeople;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SessionRoster extends Component
{
    use InteractsWithPersonAccess;

    public Booking $booking;

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

        return view('filament.team.session-roster', [
            'people' => $this->people($isLead),
            'showQualified' => $isLead && $this->requiresQualification(),
            'showKeys' => $isLead && $this->booking->requiredKeys()->isNotEmpty(),
            'showAccessDetails' => $isLead,
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

        $person = collect($this->people(includeAccessDetails: true))->firstWhere('id', $userId);

        if (! is_array($person)) {
            return false;
        }

        return match ($panel) {
            'keys' => filled($person['key_badge']),
            'qualifications' => $person['qualified'],
            default => false,
        };
    }

    /**
     * @return list<array{id: string, name: string, qualified: bool, key_badge: ?string}>
     */
    private function people(bool $includeAccessDetails): array
    {
        $this->booking->loadMissing($includeAccessDetails
            ? ['activity.qualificationRequirements', 'attendees']
            : ['attendees']);

        $attendees = $this->booking->attendees->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $badges = $includeAccessDetails ? BookingPeople::keyBadges($this->booking, $attendees) : [];
        $activity = $includeAccessDetails ? $this->booking->activity : null;
        $showQualified = $activity instanceof Activity && $activity->requiresQualification();

        return $attendees
            ->map(fn (User $person): array => [
                'id' => $person->getKey(),
                'name' => $person->name,
                'qualified' => $includeAccessDetails && $showQualified && $activity->metBy($person),
                'key_badge' => $badges[$person->getKey()] ?? null,
            ])
            ->all();
    }

    private function requiresQualification(): bool
    {
        $activity = $this->booking->activity;

        return $activity instanceof Activity && $activity->requiresQualification();
    }
}
