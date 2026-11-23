<?php

namespace App\Enums;

use App\Support\Palette;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MerchandiseAvailability: string implements HasColor, HasLabel
{
    case Available = 'available';
    case Mothballed = 'mothballed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Available => __('Available'),
            self::Mothballed => __('Mothballed'),
        };
    }

    /**
     * @return array<int, string>
     */
    public function getColor(): array
    {
        return match ($this) {
            self::Available => Palette::positive(),
            self::Mothballed => Palette::neutral(),
        };
    }
}
