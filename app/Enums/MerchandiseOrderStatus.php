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
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            // Member-facing: “submitted” is a system event; they care that approval is pending.
            self::Submitted => __('Pending approval'),
            self::Approved => __('Approved'),
            self::Rejected => __('Rejected'),
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
            self::Approved => Palette::positive(),
            self::Rejected, self::Cancelled => Palette::destructive(),
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Submitted => Heroicon::OutlinedClock,
            self::Approved => Heroicon::OutlinedCheckCircle,
            self::Rejected => Heroicon::OutlinedNoSymbol,
            self::Cancelled => Heroicon::OutlinedXCircle,
        };
    }
}
