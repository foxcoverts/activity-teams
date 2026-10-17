<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class SetupAccount extends Component
{
    public User $user;

    public string $name = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(User $user): void
    {
        abort_unless(request()->hasValidSignature(), 403);
        abort_if($user->isActive(), 403, __('This account is already set up.'));

        $this->user = $user;
        $this->name = $user->name;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $this->user->forceFill([
            'name' => $this->name,
            'password' => $this->password,
            'email_verified_at' => $this->user->email_verified_at ?? now(),
        ])->save();

        Auth::login($this->user);

        $this->redirect('/');
    }

    public function render(): View
    {
        return view('livewire.setup-account')
            ->title(__('Setup account'));
    }
}
