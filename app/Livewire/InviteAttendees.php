<?php

namespace App\Livewire;

use App\Actions\InviteAttendeesAction;
use App\Enums\BookingAttendeeStatus;
use App\Enums\MailChannel;
use App\Enums\MembershipRole;
use App\Livewire\Concerns\InteractsWithPersonAccess;
use App\Models\Booking;
use App\Models\TeamMembership;
use App\Models\User;
use App\Support\Avatars;
use App\Support\BookingPeople;
use App\Support\MailPreference;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;

class InviteAttendees extends Component
{
    use InteractsWithPersonAccess;

    public Booking $booking;

    /** @var list<string> */
    public array $queued = [];

    public bool $qualifiedOnly = false;

    public bool $keyHoldersOnly = false;

    public bool $membersOnly = false;

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

    public function toggleKeyHolders(): void
    {
        if (! $this->bookingNeedsKeys()) {
            return;
        }

        $this->keyHoldersOnly = ! $this->keyHoldersOnly;
    }

    public function toggleMembersOnly(): void
    {
        $this->membersOnly = ! $this->membersOnly;
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

        return view('filament.bookings.invite-attendees', [
            'queuedPeople' => $this->queuedPeople(),
            'pickerPeople' => $picker,
            'showQualifiedFilter' => $this->requiresQualification(),
            'showQualified' => $this->requiresQualification(),
            'showKeyFilter' => $this->bookingNeedsKeys(),
            'showKeys' => $this->bookingNeedsKeys(),
            'canAddShown' => collect($picker)->contains(fn (array $person): bool => $person['can_add']),
            'access' => $this->accessView(),
        ]);
    }

    /**
     * @return list<array{id: string, name: string, under_18: bool, is_parent: bool, qualified: bool, key_badge: ?string, mail_off: bool, status: ?BookingAttendeeStatus, can_add: bool}>
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
     * @return list<array{id: string, name: string, under_18: bool, is_parent: bool, qualified: bool, key_badge: ?string, mail_off: bool, status: ?BookingAttendeeStatus, can_add: bool}>
     */
    public function pickerPeople(): array
    {
        return $this->roster()
            ->reject(fn (array $person): bool => in_array($person['id'], $this->queued, true))
            ->filter(function (array $person): bool {
                if ($this->qualifiedOnly && ! $person['qualified']) {
                    return false;
                }

                if ($this->keyHoldersOnly && $person['key_badge'] !== 'holder') {
                    return false;
                }

                if ($this->membersOnly && $person['is_parent']) {
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
            ->filter(fn (array $person): bool => $person['qualified'] && $person['can_add'] && ! $person['is_parent'])
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
        return $this->booking->requiresQualification();
    }

    private function allowed(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can('update', $this->booking);
    }

    protected function accessBooking(): Booking
    {
        return $this->booking;
    }

    protected function accessPersonAllowed(string $userId, string $panel): bool
    {
        $person = $this->roster()->firstWhere('id', $userId);

        if (! is_array($person)) {
            return false;
        }

        return match ($panel) {
            'keys' => filled($person['key_badge']),
            'qualifications' => $person['qualified'],
            default => false,
        };
    }

    private function bookingNeedsKeys(): bool
    {
        return $this->booking->requiredLocks()->isNotEmpty();
    }

    /**
     * @return Collection<int, array{id: string, name: string, under_18: bool, is_parent: bool, qualified: bool, key_badge: ?string, mail_off: bool, status: ?BookingAttendeeStatus, can_add: bool}>
     */
    private function roster(): Collection
    {
        $booking = $this->booking;
        $settingsForBooking = function (HasMany $query) use ($booking): void {
            $query
                ->where('subject_type', $booking->getMorphClass())
                ->where('subject_id', $booking->getKey());
        };

        $this->booking->loadMissing([
            'team',
            'activity.activityAudiences.qualificationRequirements',
            'attendees.qualifications',
            'attendees.notificationSettings' => $settingsForBooking,
        ]);

        $members = $this->booking->team?->members()
            ->where('suspended', false)
            ->whereNotNull('email_verified_at')
            ->with([
                'qualifications',
                'notificationSettings' => $settingsForBooking,
            ])
            ->ordered()
            ->get()
            ->keyBy(fn (User $user): string => $user->getKey())
            ?? collect();

        $attendees = $this->booking->attendees->keyBy(fn (User $user): string => $user->getKey());
        $teamMemberRoles = $members->mapWithKeys(
            fn (User $member): array => [$member->getKey() => $member->pivot->role],
        );
        $attendeesNotInInviteableMembers = $attendees->keys()->diff($members->keys());

        if ($this->booking->team !== null && $attendeesNotInInviteableMembers->isNotEmpty()) {
            $teamMemberRoles = $teamMemberRoles->merge(
                TeamMembership::query()
                    ->where('team_id', $this->booking->team->getKey())
                    ->whereNull('left_at')
                    ->whereIn('user_id', $attendeesNotInInviteableMembers->all())
                    ->get(['user_id', 'role'])
                    ->mapWithKeys(fn (TeamMembership $membership): array => [
                        (string) $membership->user_id => $membership->role,
                    ]),
            );
        }

        $showQualified = $this->booking->requiresQualification();
        $badges = BookingPeople::keyBadges(
            $this->booking,
            $members->values()->merge($attendees->values())->unique(fn (User $user): string => $user->getKey()),
        );

        return $members->keys()
            ->merge($attendees->keys())
            ->unique()
            ->map(function (string $id) use ($members, $attendees, $showQualified, $badges, $teamMemberRoles): array {
                $member = $members->get($id);
                $attendee = $attendees->get($id);
                $user = $member ?? $attendee;
                $onBooking = $attendee instanceof User;

                return [
                    'id' => $id,
                    'name' => $user->name,
                    'avatar_url' => Avatars::userUrl($user, 64),
                    'under_18' => $user->isUnder18(),
                    'is_parent' => $teamMemberRoles->get($id) === MembershipRole::Parent,
                    'qualified' => $showQualified && $this->booking->metBy($user),
                    'key_badge' => $badges[$id] ?? null,
                    'mail_off' => ! MailPreference::allows($user, MailChannel::BookingsMail, $this->booking),
                    'status' => $onBooking ? $attendee->attendance?->status : null,
                    'can_add' => $member instanceof User && ! $onBooking,
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }
}
