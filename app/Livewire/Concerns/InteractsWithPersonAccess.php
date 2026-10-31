<?php

namespace App\Livewire\Concerns;

use App\Models\Booking;
use App\Models\User;
use App\Support\BookingPeople;

trait InteractsWithPersonAccess
{
    public ?string $accessPersonId = null;

    public ?string $accessPanel = null;

    public bool $showOtherQualifications = false;

    public ?string $qualificationId = null;

    abstract protected function accessBooking(): Booking;

    abstract protected function accessPersonAllowed(string $userId, string $panel): bool;

    public function openKeys(string $userId): void
    {
        $this->openAccess($userId, 'keys');
    }

    public function openQualifications(string $userId): void
    {
        $this->openAccess($userId, 'qualifications');
    }

    public function closeAccess(): void
    {
        $this->resetAccess();
    }

    public function toggleOtherQualifications(): void
    {
        abort_unless($this->accessPanel === 'qualifications', 404);

        $this->showOtherQualifications = ! $this->showOtherQualifications;
        $this->qualificationId = null;
    }

    public function openQualification(string $qualificationId): void
    {
        abort_unless($this->accessPanel === 'qualifications', 404);

        $groups = $this->qualificationGroups();
        $known = collect($groups['relevant'])->merge($groups['other'])->contains('id', $qualificationId);
        abort_unless($known, 404);

        $this->qualificationId = $qualificationId;
    }

    public function closeQualification(): void
    {
        $this->qualificationId = null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function accessView(): ?array
    {
        if ($this->accessPersonId === null || $this->accessPanel === null) {
            return null;
        }

        $person = User::query()->find($this->accessPersonId);

        if (! $person instanceof User) {
            return null;
        }

        $groups = $this->qualificationGroups();
        $detail = $this->qualificationId === null
            ? null
            : collect($groups['relevant'])->merge($groups['other'])->firstWhere('id', $this->qualificationId);

        return [
            'name' => $person->name,
            'panel' => $this->accessPanel,
            'rings' => BookingPeople::ringLines($person),
            'relevant' => $groups['relevant'],
            'other' => $this->showOtherQualifications ? $groups['other'] : [],
            'show_other' => $this->showOtherQualifications,
            'has_other' => $groups['other'] !== [],
            'detail' => $detail,
        ];
    }

    protected function openAccess(string $userId, string $panel): void
    {
        abort_unless($this->accessPersonAllowed($userId, $panel), 403);

        $this->accessPersonId = $userId;
        $this->accessPanel = $panel;
        $this->showOtherQualifications = false;
        $this->qualificationId = null;
    }

    protected function resetAccess(): void
    {
        $this->accessPersonId = null;
        $this->accessPanel = null;
        $this->showOtherQualifications = false;
        $this->qualificationId = null;
    }

    /**
     * @return array{relevant: list<array<string, mixed>>, other: list<array<string, mixed>>}
     */
    protected function qualificationGroups(): array
    {
        $person = User::query()->find($this->accessPersonId);
        $booking = $this->accessBooking();
        $booking->loadMissing('activity.activityAudiences.qualificationRequirements');

        if (! $person instanceof User) {
            return ['relevant' => [], 'other' => []];
        }

        return BookingPeople::qualifications($person, $booking);
    }
}
