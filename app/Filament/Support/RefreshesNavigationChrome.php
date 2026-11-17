<?php

namespace App\Filament\Support;

use Livewire\Component;

final class RefreshesNavigationChrome
{
    public static function dispatch(?Component $livewire): void
    {
        if (! $livewire instanceof Component) {
            return;
        }

        $livewire->dispatch('refresh-sidebar');
        $livewire->dispatch('refresh-topbar');
    }
}
