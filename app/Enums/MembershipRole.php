<?php

namespace App\Enums;

use App\Support\Palette;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum MembershipRole: string implements HasColor, HasIcon, HasLabel
{
    case Manager = 'manager';
    case Member = 'member';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manager => __('Manager'),
            self::Member => __('Member'),
        };
    }

    /**
     * @return array<int, string>
     */
    public function getColor(): array
    {
        return match ($this) {
            self::Manager => Palette::manager(),
            self::Member => Palette::neutral(),
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Manager => Heroicon::Star,
            self::Member => Heroicon::User,
        };
    }
}
