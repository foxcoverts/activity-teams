<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Unexposed;

class Login extends BaseLogin
{
    #[Unexposed]
    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getHomeUrl() ?? Filament::getUrl());
        }

        $this->form->fill();
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getSubheading();
        }

        return null;
    }
}
