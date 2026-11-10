<?php

namespace App\Enums;

use App\Support\Palette;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum TaskPriority: int implements HasColor, HasIcon, HasLabel
{
    case High = 1;
    case Medium = 5;
    case Low = 9;

    public function getLabel(): string
    {
        return match ($this) {
            self::High => __('High'),
            self::Medium => __('Medium'),
            self::Low => __('Low'),
        };
    }

    /**
     * @return array<int, string>
     */
    public function getColor(): array
    {
        return match ($this) {
            self::High => Palette::destructive(),
            self::Medium => Palette::neutral(),
            self::Low => Palette::manager(),
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::High => Heroicon::OutlinedExclamationTriangle,
            self::Medium => Heroicon::OutlinedMinus,
            self::Low => Heroicon::OutlinedArrowDown,
        };
    }
}
