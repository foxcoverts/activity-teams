<?php

namespace App\Enums;

use App\Support\Palette;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum NewsPostStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Deleted = 'deleted';

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Draft => Heroicon::Pencil,
            self::Scheduled => Heroicon::Clock,
            self::Published => Heroicon::CheckCircle,
            self::Deleted => Heroicon::Trash,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Scheduled => __('Scheduled'),
            self::Published => __('Published'),
            self::Deleted => __('Deleted'),
        };
    }

    public function getColor(): array
    {
        return match ($this) {
            self::Draft => Palette::neutral(),
            self::Scheduled => Palette::uncertain(),
            self::Published => Palette::positive(),
            self::Deleted => Palette::destructive(),
        };
    }
}
