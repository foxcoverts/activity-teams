<?php

namespace App\Enums;

use App\Support\Palette;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum MerchandiseOrderStatus: string implements HasColor, HasIcon, HasLabel
{
    case Submitted = 'submitted';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Submitted => __('Submitted'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /**
     * @return array<int, string>
     */
    public function getColor(): array
    {
        return match ($this) {
            self::Submitted => Palette::uncertain(),
            self::Cancelled => Palette::destructive(),
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Submitted => Heroicon::OutlinedPaperAirplane,
            self::Cancelled => Heroicon::OutlinedXCircle,
        };
    }
}
