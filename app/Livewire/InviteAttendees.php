<?php

namespace App\Livewire;

use App\Actions\InviteAttendeesAction;
use App\Enums\BookingAttendeeStatus;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;

class InviteAttendees extends Component
{
    public Booking $booking;

    /** @var list<string> */
    public array $queued = [];

    public bool $qualifiedOnly = false;

    public string $name = '';

    public function mount(Booking $booking): void
    {
        $this->booking = $booking;
        abort_unless($this->allowed(), 403);

        $this->queued = $this->defaultQueued();
    }

    public function toggleQualified(): void
    {
        if (! $this->requiresQualification()) {
            return;
        }

        $this->qualifiedOnly = ! $this->qualifiedOnly;
    }

    public function add(string $userId): void
    {
        if (! $this->canQueue($userId) || in_array($userId, $this->queued, true)) {
            return;
        }

        $this->queued[] = $userId;
    }

    public function remove(string $userId): void
    {
        $this->queued = array_values(array_filter(
            $this->queued,
            fn (string $id): bool => $id !== $userId,
        ));
    }

    public function addShown(): void
    {
        foreach ($this->pickerPeople() as $person) {
            if ($person['can_add']) {
                $this->add($person['id']);
            }
        }
    }

    public function send(InviteAttendeesAction $action): void
    {
        abort_unless($this->allowed(), 403);

        $ids = array_values(array_filter(
            $this->queued,
            fn (string $id): bool => $this->canQueue($id),
        ));

        $actor = auth()->user();

        if ($ids === [] || ! $actor instanceof User) {
            return;
        }

        $action($this->booking, $ids, $actor);

        $this->dispatch('invites-sent');
    }

    public function cancel(): void
    {
        $this->dispatch('close-invite');
    }

    public function render(): View
    {
        $picker = $this->pickerPeople();

        return view('filament.manage.invite-attendees', [
            'queuedPeople' => $this->queuedPeople(),
            'pickerPeople' => $picker,
            'showQualifiedFilter' => $this->requiresQualification(),
            'canAddShown' => collect($picker)->contains(fn (array $person): bool => $person['can_add']),
        ]);
    }

    /**
     * @return list<array{id: string, name: string, qualified: bool, status: ?BookingAttendeeStatus, can_add: bool}>
     */
    public function queuedPeople(): array
    {
        $roster = $this->roster();

        return collect($this->queued)
            ->map(fn (string $id): ?array => $roster->firstWhere('id', $id))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: string, name: string, qualified: bool, status: ?BookingAttendeeStatus, can_add: bool}>
     */
    public function pickerPeople(): array
    {
        return $this->roster()
            ->reject(fn (array $person): bool => in_array($person['id'], $this->queued, true))
            ->filter(function (array $person): bool {
                if ($this->qualifiedOnly && ! $person['qualified']) {
                    return false;
                }

                $name = trim($this->name);

                return $name === '' || Str::contains($person['name'], $name, ignoreCase: true);
            })
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function defaultQueued(): array
    {
        if (! $this->requiresQualification()) {
            return [];
        }

        return $this->roster()
            ->filter(fn (array $person): bool => $person['qualified'] && $person['can_add'])
            ->map(fn (array $person): string => $person['id'])
            ->values()
            ->all();
    }

    private function canQueue(string $userId): bool
    {
        $person = $this->roster()->firstWhere('id', $userId);

        return is_array($person) && $person['can_add'];
    }

    private function requiresQualification(): bool
    {
        return $this->activity() instanceof Activity && $this->activity()->requiresQualification();
    }

    private function activity(): ?Activity
    {
        $this->booking->loadMissing('activity');

        $activity = $this->booking->activity;

        return $activity instanceof Activity ? $activity : null;
    }

    private function allowed(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('update', $this->booking);
    }

    /**
     * @return Collection<int, array{id: string, name: string, qualified: bool, status: ?BookingAttendeeStatus, can_add: bool}>
     */
    private function roster(): Collection
    {
        $this->booking->loadMissing(['team', 'activity.qualificationRequirements', 'attendees']);

        $members = $this->booking->team?->members()
            ->where('suspended', false)
            ->whereNotNull('email_verified_at')
            ->ordered()
            ->get()
            ->keyBy(fn (User $user): string => $user->getKey())
            ?? collect();

        $attendees = $this->booking->attendees->keyBy(fn (User $user): string => $user->getKey());

        $activity = $this->activity();
        $showQualified = $activity instanceof Activity && $activity->requiresQualification();

        return $members->keys()
            ->merge($attendees->keys())
            ->unique()
            ->map(function (string $id) use ($members, $attendees, $activity, $showQualified): array {
                $member = $members->get($id);
                $attendee = $attendees->get($id);
                $user = $member ?? $attendee;
                $onBooking = $attendee instanceof User;

                return [
                    'id' => $id,
                    'name' => $user->name,
                    'qualified' => $showQualified && $activity->metBy($user),
                    'status' => $onBooking ? $attendee->attendance?->status : null,
                    'can_add' => $member instanceof User && ! $onBooking,
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }
}
