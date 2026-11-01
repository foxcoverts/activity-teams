<?php

namespace App\Filament\Member;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    case Team = 'team';
    case Personal = 'personal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Team => __('Team'),
            self::Personal => __('Personal'),
        };
    }
}
