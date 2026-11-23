<?php

namespace App\Filament\Teams;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    case Merchandise = 'merchandise';
    case Manage = 'manage';
    case Setup = 'setup';
    case Admin = 'admin';
    case Personal = 'personal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Merchandise => __('Merchandise'),
            self::Manage => __('Manage'),
            self::Setup => __('Setup'),
            self::Admin => __('Admin'),
            self::Personal => __('Personal'),
        };
    }
}
