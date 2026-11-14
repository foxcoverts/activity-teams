<?php

namespace App\Filament\Member;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    case Personal = 'personal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Personal => __('Personal'),
        };
    }
}
