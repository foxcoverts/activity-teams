<?php

namespace App\Filament\Manage;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    case Team = 'team';
    case Setup = 'setup';

    public function getLabel(): string
    {
        return match ($this) {
            self::Team => __('Team'),
            self::Setup => __('Setup'),
        };
    }
}
