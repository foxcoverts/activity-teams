<?php

namespace App\Livewire;

use App\Actions\AcknowledgePersonalDataAction;
use App\Enums\PersonalDataSection;
use App\Filament\Teams\Pages\Leaders;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Shows one person's phone and/or emergency contact to someone on the same booking (or a team
 * manager), once the viewer has acknowledged each US-58 section. Details are never part of the
 * component state.
 */
class BookingContactDetails extends Component
{
    public Booking $booking;

    public User $person;

    public function mount(): void
    {
        abort_unless($this->mayOpen(), 403);
    }

    public function acknowledge(string $section): void
    {
        $section = PersonalDataSection::tryFrom($section);

        abort_unless(
            $section instanceof PersonalDataSection
                && $this->sectionAllowed($section)
                && $this->sectionHasDetails($section),
            403,
        );

        app(AcknowledgePersonalDataAction::class)(auth()->user(), $this->person, $section);
    }

    public function render(): View
    {
        $viewer = auth()->user();
        $phoneAllowed = $this->canViewPhone();
        $emergencyAllowed = $this->canViewEmergency();
        $phoneOnFile = $phoneAllowed && filled($this->person->phone);
        $emergencyOnFile = $emergencyAllowed && filled($this->person->emergency_phone);

        $phoneAcknowledged = $phoneOnFile && $viewer instanceof User
            && $viewer->hasAcknowledgedPersonalData($this->person, PersonalDataSection::ContactDetails);
        $emergencyAcknowledged = $emergencyOnFile && $viewer instanceof User
            && $viewer->hasAcknowledgedPersonalData($this->person, PersonalDataSection::EmergencyContact);

        $isManager = $viewer instanceof User
            && $this->booking->team !== null
            && $viewer->manages($this->booking->team);

        return view('livewire.booking-contact-details', [
            'phoneOnFile' => $phoneOnFile,
            'emergencyAllowed' => $emergencyAllowed,
            'emergencyOnFile' => $emergencyOnFile,
            'phoneAcknowledged' => $phoneAcknowledged,
            'emergencyAcknowledged' => $emergencyAcknowledged,
            'needsPhoneGate' => $phoneOnFile && ! $phoneAcknowledged,
            'needsEmergencyGate' => $emergencyOnFile && ! $emergencyAcknowledged,
            'phone' => $phoneAcknowledged ? $this->person->phone : null,
            'emergencyName' => $emergencyAcknowledged ? $this->person->emergency_name : null,
            'emergencyPhone' => $emergencyAcknowledged ? $this->person->emergency_phone : null,
            'isManager' => $isManager,
            'leadersUrl' => $isManager || $this->booking->team === null
                ? null
                : Leaders::getUrl(panel: 'teams', tenant: $this->booking->team),
        ]);
    }

    private function mayOpen(): bool
    {
        return $this->canViewPhone() || $this->canViewEmergency();
    }

    private function sectionAllowed(PersonalDataSection $section): bool
    {
        return match ($section) {
            PersonalDataSection::ContactDetails => $this->canViewPhone(),
            PersonalDataSection::EmergencyContact => $this->canViewEmergency(),
        };
    }

    private function sectionHasDetails(PersonalDataSection $section): bool
    {
        return match ($section) {
            PersonalDataSection::ContactDetails => filled($this->person->phone),
            PersonalDataSection::EmergencyContact => filled($this->person->emergency_phone),
        };
    }

    private function canViewPhone(): bool
    {
        return $this->allows('viewPhone');
    }

    private function canViewEmergency(): bool
    {
        return $this->allows('viewEmergencyContact');
    }

    private function allows(string $ability): bool
    {
        $viewer = auth()->user();

        if (! $viewer instanceof User) {
            return false;
        }

        $attendance = $this->booking->attendees()->whereKey($this->person->getKey())->first()?->attendance;

        if (! $attendance instanceof BookingAttendance) {
            return false;
        }

        return Gate::forUser($viewer)->allows($ability, $attendance);
    }
}
