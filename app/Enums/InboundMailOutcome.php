<?php

namespace App\Enums;

use App\Support\Palette;
use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum InboundMailOutcome: string implements HasColor, HasIcon, HasLabel
{
    case Applied = 'applied';
    case Resent = 'resent';
    case Queued = 'queued';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Applied => __('Applied'),
            self::Resent => __('Resent calendar'),
            self::Queued => __('Needs review'),
            self::Failed => __('Failed'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Applied, self::Resent => Palette::positive(),
            self::Queued => Palette::uncertain(),
            self::Failed => Palette::destructive(),
        };
    }

    public function getIcon(): string|BackedEnum|null
    {
        return match ($this) {
            self::Applied => Heroicon::OutlinedCheckCircle,
            self::Resent => Heroicon::OutlinedArrowPath,
            self::Queued => Heroicon::OutlinedInboxArrowDown,
            self::Failed => Heroicon::OutlinedExclamationTriangle,
        };
    }

    public function needsReview(): bool
    {
        return match ($this) {
            self::Queued, self::Failed => true,
            self::Applied, self::Resent => false,
        };
    }
}
