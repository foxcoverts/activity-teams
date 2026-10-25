<?php

namespace App\Filament\Admin;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    case Site = 'site';

    public function getLabel(): string
    {
        return match ($this) {
            self::Site => __('Site admin'),
        };
    }
}
