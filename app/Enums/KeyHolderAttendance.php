<?php

namespace App\Enums;

use App\Support\Palette;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum KeyHolderAttendance: string implements HasColor, HasIcon, HasLabel
{
    case Attending = 'attending';
    case Missing = 'missing';

    public function getLabel(): string
    {
        return match ($this) {
            self::Attending => __('Key holder attending'),
            self::Missing => __('No key holder attending'),
        };
    }

    /**
     * @return array<int, string>
     */
    public function getColor(): array
    {
        return match ($this) {
            self::Attending => Palette::positive(),
            self::Missing => Palette::uncertain(),
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Attending => Heroicon::Key,
            self::Missing => Heroicon::OutlinedKey,
        };
    }
}
