<?php

namespace App\Enums;

use App\Enums\Concerns\CoversValues;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum ScoutPermitActivity: string implements HasIcon, HasLabel
{
    use CoversValues;

    case ClimbingAndAbseiling = 'Climbing and Abseiling';
    case Archery = 'Archery';

    public static function isClimbing(mixed $value): bool
    {
        if ($value instanceof self) {
            return $value === self::ClimbingAndAbseiling;
        }

        return $value === self::ClimbingAndAbseiling->value;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::ClimbingAndAbseiling => __('Climbing and abseiling'),
            self::Archery => __('Archery'),
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::ClimbingAndAbseiling => Heroicon::ArrowUp,
            self::Archery => Heroicon::Flag,
        };
    }
}
