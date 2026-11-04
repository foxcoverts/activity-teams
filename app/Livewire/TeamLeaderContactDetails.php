<?php

namespace App\Livewire;

use App\Actions\AcknowledgePersonalDataAction;
use App\Enums\PersonalDataSection;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Shows a team manager's email and phone to a current member of the same team, once the viewer has
 * acknowledged the US-58 notice. The details are never part of the component state.
 */
class TeamLeaderContactDetails extends Component
{
    public Team $team;

    public User $person;

    public function mount(): void
    {
        abort_unless($this->allowed(), 403);
    }

    public function acknowledge(): void
    {
        abort_unless($this->allowed(), 403);

        app(AcknowledgePersonalDataAction::class)(auth()->user(), $this->person, PersonalDataSection::ContactDetails);
    }

    public function render(): View
    {
        $viewer = auth()->user();
        $allowed = $this->allowed();
        $acknowledged = $allowed && $viewer instanceof User
            && $viewer->hasAcknowledgedPersonalData($this->person, PersonalDataSection::ContactDetails);

        return view('livewire.team-leader-contact-details', [
            'allowed' => $allowed,
            'acknowledged' => $acknowledged,
            'details' => $acknowledged ? [
                ['label' => __('Email'), 'value' => $this->person->email, 'kind' => 'email'],
                ['label' => __('Phone'), 'value' => $this->person->phone, 'kind' => 'tel'],
            ] : [],
        ]);
    }

    private function allowed(): bool
    {
        $viewer = auth()->user();

        return $viewer instanceof User
            && $viewer->isCurrentMemberOf($this->team)
            && $this->team->activeManagers()->whereKey($this->person->getKey())->exists();
    }
}
