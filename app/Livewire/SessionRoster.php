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

        abort_unless($actor instanceof User && $booking->isLead($actor), 403);

        $this->booking = $booking;
    }

    public function render(): View
    {
        return view('filament.team.session-roster', [
            'people' => $this->people(),
            'showQualified' => $this->requiresQualification(),
            'showKeys' => $this->booking->requiredKeys()->isNotEmpty(),
            'access' => $this->accessView(),
        ]);
    }

    protected function accessBooking(): Booking
    {
        return $this->booking;
    }

    protected function accessPersonAllowed(string $userId, string $panel): bool
    {
        $person = collect($this->people())->firstWhere('id', $userId);

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
    private function people(): array
    {
        $this->booking->loadMissing(['activity.qualificationRequirements', 'attendees']);

        $attendees = $this->booking->attendees->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $badges = BookingPeople::keyBadges($this->booking, $attendees);
        $activity = $this->booking->activity;
        $showQualified = $activity instanceof Activity && $activity->requiresQualification();

        return $attendees
            ->map(fn (User $person): array => [
                'id' => $person->getKey(),
                'name' => $person->name,
                'qualified' => $showQualified && $activity->metBy($person),
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
