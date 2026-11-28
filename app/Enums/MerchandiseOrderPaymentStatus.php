<?php

namespace App\Enums;

use App\Support\Palette;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum MerchandiseOrderPaymentStatus: string implements HasColor, HasIcon, HasLabel
{
    case None = 'none';
    case Unpaid = 'unpaid';
    case Paid = 'paid';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => __('—'),
            self::Unpaid => __('Unpaid'),
            self::Paid => __('Paid'),
        };
    }

    /**
     * @return array<int, string>|string
     */
    public function getColor(): array|string
    {
        return match ($this) {
            self::None => Palette::neutral(),
            self::Unpaid => Palette::uncertain(),
            self::Paid => Palette::positive(),
        };
    }

    public function getIcon(): ?Heroicon
    {
        return match ($this) {
            self::None => null,
            self::Unpaid => Heroicon::OutlinedBanknotes,
            self::Paid => Heroicon::OutlinedCheckCircle,
        };
    }
}
