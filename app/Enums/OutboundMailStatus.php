<?php

namespace App\Enums;

use App\Support\Palette;
use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum OutboundMailStatus: string implements HasColor, HasIcon, HasLabel
{
    case Sent = 'sent';
    case Bounced = 'bounced';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sent => __('Sent'),
            self::Bounced => __('Bounced'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Sent => Palette::positive(),
            self::Bounced => Palette::destructive(),
        };
    }

    public function getIcon(): string|BackedEnum|null
    {
        return match ($this) {
            self::Sent => Heroicon::OutlinedPaperAirplane,
            self::Bounced => Heroicon::OutlinedExclamationTriangle,
        };
    }
}
