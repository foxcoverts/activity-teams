<?php

namespace App\Livewire;

use App\Actions\AcknowledgePersonalDataAction;
use App\Enums\PersonalDataSection;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Shows one person's phone or emergency contact to someone on the same booking, once the viewer has
 * acknowledged the US-58 notice. The details are never part of the component state.
 */
class BookingContactDetails extends Component
{
    public Booking $booking;

    public User $person;

    public string $kind = 'phone';

    public function mount(): void
    {
        abort_unless($this->allowed(), 403);
    }

    public function acknowledge(): void
    {
        abort_unless($this->allowed(), 403);

        app(AcknowledgePersonalDataAction::class)(auth()->user(), $this->person, $this->section());
    }

    public function render(): View
    {
        $viewer = auth()->user();
        $allowed = $this->allowed();
        $acknowledged = $allowed && $viewer instanceof User
            && $viewer->hasAcknowledgedPersonalData($this->person, $this->section());

        return view('livewire.booking-contact-details', [
            'allowed' => $allowed,
            'acknowledged' => $acknowledged,
            'name' => $this->person->name,
            'details' => $acknowledged ? $this->details() : [],
        ]);
    }

    private function section(): PersonalDataSection
    {
        return $this->kind === 'emergency'
            ? PersonalDataSection::EmergencyContact
            : PersonalDataSection::ContactDetails;
    }

    private function allowed(): bool
    {
        $viewer = auth()->user();

        if (! $viewer instanceof User) {
            return false;
        }

        $attendance = $this->booking->attendees()->whereKey($this->person->getKey())->first()?->attendance;

        if (! $attendance instanceof BookingAttendance) {
            return false;
        }

        return Gate::forUser($viewer)->allows(
            $this->kind === 'emergency' ? 'viewEmergencyContact' : 'viewPhone',
            $attendance,
        );
    }

    /**
     * @return list<array{label: string, value: ?string, tel: bool}>
     */
    private function details(): array
    {
        if ($this->kind === 'emergency') {
            return [
                ['label' => __('Emergency contact name'), 'value' => $this->person->emergency_name, 'tel' => false],
                ['label' => __('Emergency contact phone'), 'value' => $this->person->emergency_phone, 'tel' => true],
            ];
        }

        return [['label' => __('Phone'), 'value' => $this->person->phone, 'tel' => true]];
    }
}
