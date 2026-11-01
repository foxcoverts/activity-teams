<?php

namespace App\Filament\Manage;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    case Team = 'team';

    public function getLabel(): string
    {
        return match ($this) {
            self::Team => __('Team'),
        };
    }
}
