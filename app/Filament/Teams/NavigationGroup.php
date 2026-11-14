<?php

namespace App\Filament\Teams;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    case Setup = 'setup';
    case Admin = 'admin';
    case Personal = 'personal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Setup => __('Setup'),
            self::Admin => __('Admin'),
            self::Personal => __('Personal'),
        };
    }
}
