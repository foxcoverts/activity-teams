<?php

namespace App\Livewire;

use App\Enums\MailChannel;
use App\Enums\MailGroup;
use App\Filament\Member\Pages\NotificationSettings;
use App\Models\Booking;
use App\Models\User;
use App\Support\MailPreference;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ManageMailPreferences extends Component
{
    public User $user;

    public MailGroup $group;

    public ?Booking $booking = null;

    public bool $useGlobal = false;

    /**
     * @var array<string, string>
     */
    public array $preferences = [];

    public bool $saved = false;

    public function mount(User $user, ?Booking $booking = null, ?MailGroup $group = null): void
    {
        abort_if($user->trashed() || $user->isSuspended(), 403);

        if ($booking instanceof Booking) {
            abort_unless($this->canManageBooking($user, $booking), 403);

            $this->user = $user;
            $this->booking = $booking->loadMissing(['activity', 'venue']);
            $this->group = MailGroup::Bookings;
            $this->useGlobal = false;
            $this->preferences = MailPreference::displayState($user, MailGroup::Bookings, $booking);

            return;
        }

        abort_unless($group instanceof MailGroup && $group->isReleased() && ! $group->alwaysOn(), 404);
        abort_if($group->hasSubject(), 404);

        $this->user = $user;
        $this->group = $group;
        $this->useGlobal = true;
        $this->preferences = MailPreference::displayState($user, $group);
    }

    public function updatedUseGlobal(): void
    {
        $this->saved = false;
        $this->preferences = MailPreference::displayState($this->user, $this->group, $this->subject());
    }

    public function updated(string $property): void
    {
        $master = $this->group->masterChannel();

        if ($master instanceof MailChannel && $property === 'preferences.'.$master->formKey()) {
            $this->syncMasterDisplay();
        }
    }

    public function save(): void
    {
        MailPreference::save($this->user, $this->group, $this->preferences, $this->subject());
        $this->user->refresh();
        $this->saved = true;
        $this->syncMasterDisplay();
    }

    public function resetOverride(): void
    {
        $subject = $this->subject();

        if ($subject === null) {
            MailPreference::save($this->user, $this->group, []);

            $this->user->refresh();
            $this->saved = true;
            $this->syncMasterDisplay();

            return;
        }

        MailPreference::forgetSubject($this->user, $subject);
        $this->user->unsetRelation('notificationSettings');
        $this->saved = true;
        $this->syncMasterDisplay();
    }

    /**
     * @return list<MailChannel>
     */
    public function channels(): array
    {
        return $this->group->shownChannels();
    }

    public function inheritedLabel(MailChannel $channel): string
    {
        $on = MailPreference::inheritedValue($this->user, $channel, $this->subject());

        return __('Default (:state)', ['state' => $on ? __('On') : __('Off')]);
    }

    public function masterOff(): bool
    {
        return MailPreference::formMasterOff($this->user, $this->group, $this->preferences, $this->subject());
    }

    public function allSettingsUrl(): string
    {
        return NotificationSettings::getUrl(panel: 'member');
    }

    public function heading(): string
    {
        if ($this->booking instanceof Booking && ! $this->useGlobal) {
            return __('Notifications for this booking');
        }

        return __('Notification settings');
    }

    public function render(): View
    {
        return view('livewire.manage-mail-preferences')
            ->layout('layouts.filament-form')
            ->title($this->heading());
    }

    private function syncMasterDisplay(): void
    {
        $master = $this->group->masterChannel();
        $stored = MailPreference::formState($this->user, $this->group, $this->subject());

        if ($master instanceof MailChannel) {
            $stored[$master->formKey()] = $this->preferences[$master->formKey()] ?? '';
        }

        $this->preferences = MailPreference::applyMasterDisplay(
            $this->user,
            $this->group,
            $stored,
            $this->subject(),
        );
    }

    private function subject(): ?Booking
    {
        if ($this->useGlobal || ! $this->booking instanceof Booking) {
            return null;
        }

        return $this->booking;
    }

    private function canManageBooking(User $user, Booking $booking): bool
    {
        if ($user->can('view', $booking)) {
            return true;
        }

        return $booking->attendees()->whereKey($user->getKey())->exists();
    }
}
