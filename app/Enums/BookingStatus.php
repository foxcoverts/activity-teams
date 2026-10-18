<?php

namespace App\Enums;

use App\Support\Palette;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum BookingStatus: string implements HasColor, HasIcon, HasLabel
{
    case Confirmed = 'confirmed';
    case Tentative = 'tentative';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Confirmed => __('Confirmed'),
            self::Tentative => __('Tentative'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /**
     * @return array<int, string>
     */
    public function getColor(): array
    {
        return match ($this) {
            self::Confirmed => Palette::positive(),
            self::Tentative => Palette::uncertain(),
            self::Cancelled => Palette::destructive(),
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Confirmed => Heroicon::CheckCircle,
            self::Tentative => Heroicon::QuestionMarkCircle,
            self::Cancelled => Heroicon::XCircle,
        };
    }
}
