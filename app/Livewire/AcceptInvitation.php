<?php

namespace App\Livewire;

use App\Actions\AcceptTeamInvitationAction;
use App\Filament\Schemas\ContactFields;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Support\Timezones;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * @property-read Schema $form
 */
class AcceptInvitation extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    #[Locked]
    public string $invitationId = '';

    #[Locked]
    public string $invitationUrl = '';

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $timezone = 'Europe/London';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public bool $privacy_notice_accepted = false;

    public bool $declined = false;

    public function mount(TeamInvitation $invitation): void
    {
        abort_unless(hash_equals($invitation->token, (string) request()->query('token')), 403);

        $this->invitationId = $invitation->getKey();
        $this->invitationUrl = request()->fullUrl();
        $this->name = $invitation->user->name;
        $this->email = $invitation->email;
        $this->timezone = (string) (Auth::user()?->timezone ?? config('app.timezone'));
        $this->form->fill();

        // Lets the invitee return here after signing in with an existing account.
        session()->put('url.intended', request()->fullUrl());
    }

    public function createAccount(AcceptTeamInvitationAction $accept): void
    {
        $invitation = $this->invitation();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'timezone' => ['required', 'timezone:all'],
            'privacy_notice_accepted' => ['accepted'],
        ]);

        $data = [
            ...$data,
            ...$this->form->getState(),
        ];

        $user = $accept->createAccount($invitation, $data);

        Auth::login($user);

        $this->redirect('/');
    }

    public function useMyAccount(AcceptTeamInvitationAction $accept): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $accept->claimWith($this->invitation(), $user);

        $this->redirect('/');
    }

    public function signOutAndCreateAccount(): void
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect($this->invitationUrl);
    }

    public function decline(AcceptTeamInvitationAction $accept): void
    {
        $accept->decline($this->invitation());

        $this->declined = true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function timezoneGroups(): array
    {
        return Timezones::groups();
    }

    public function render(): View
    {
        $invitation = $this->invitation();

        return view('livewire.accept-invitation', [
            'invitation' => $invitation,
            'valid' => $invitation->isPending() && ! $this->declined,
            'account' => Auth::user(),
            'privacyNoticeUrl' => ContactFields::privacyNoticeUrl(),
        ])->layout('layouts.filament-form')->title(__('Invitation'));
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ContactFields::phoneNotice(),
                ContactFields::phoneInput('phone'),
                ...ContactFields::emergencyFields(),
            ]);
    }

    private function invitation(): TeamInvitation
    {
        return TeamInvitation::query()->with(['team', 'user'])->findOrFail($this->invitationId);
    }
}
