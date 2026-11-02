<?php

namespace App\Enums;

use App\Support\Palette;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AgeGroup: string implements HasColor, HasLabel
{
    case Under18 = 'under_18';
    case Adult = 'adult';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Under18 => __('Under 18'),
            self::Adult => __('Adult'),
        };
    }

    /**
     * @return array<int, string>
     */
    public function getColor(): array
    {
        return match ($this) {
            self::Under18 => Palette::youth(),
            self::Adult => Palette::adult(),
        };
    }
}
