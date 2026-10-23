<?php

namespace App\Livewire;

use App\Actions\AcceptTeamInvitationAction;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Support\Timezones;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AcceptInvitation extends Component
{
    #[Locked]
    public string $invitationId = '';

    #[Locked]
    public string $invitationUrl = '';

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $timezone = 'Europe/London';

    public string $phone = '';

    public string $emergency_name = '';

    public string $emergency_phone = '';

    public bool $declined = false;

    public function mount(TeamInvitation $invitation): void
    {
        abort_unless(hash_equals($invitation->token, (string) request()->query('token')), 403);

        $this->invitationId = $invitation->getKey();
        $this->invitationUrl = request()->fullUrl();
        $this->name = $invitation->user->name;
        $this->email = $invitation->email;
        $this->timezone = (string) (Auth::user()?->timezone ?? config('app.timezone'));

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
            'phone' => ['nullable', 'string', 'max:20'],
            'emergency_name' => ['nullable', 'required_with:emergency_phone', 'string', 'max:100'],
            'emergency_phone' => ['nullable', 'required_with:emergency_name', 'string', 'max:20'],
        ]);

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
        ])->title(__('Invitation'));
    }

    private function invitation(): TeamInvitation
    {
        return TeamInvitation::query()->with(['team', 'user'])->findOrFail($this->invitationId);
    }
}
