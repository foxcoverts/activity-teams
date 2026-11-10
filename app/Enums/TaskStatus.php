<?php

namespace App\Enums;

use App\Support\Palette;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum TaskStatus: string implements HasColor, HasIcon, HasLabel
{
    case NeedsAction = 'needs-action';
    case InProcess = 'in-process';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::NeedsAction => __('Needs action'),
            self::InProcess => __('In process'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /**
     * @return array<int, string>
     */
    public function getColor(): array
    {
        return match ($this) {
            self::NeedsAction => Palette::uncertain(),
            self::InProcess => Palette::manager(),
            self::Completed => Palette::positive(),
            self::Cancelled => Palette::destructive(),
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::NeedsAction => Heroicon::OutlinedClock,
            self::InProcess => Heroicon::OutlinedArrowPath,
            self::Completed => Heroicon::CheckCircle,
            self::Cancelled => Heroicon::XCircle,
        };
    }

    public function isOpen(): bool
    {
        return match ($this) {
            self::NeedsAction, self::InProcess => true,
            self::Completed, self::Cancelled => false,
        };
    }
}
