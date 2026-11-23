<?php

namespace App\Enums;

use App\Support\Palette;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum IssuedMerchandiseSource: string implements HasColor, HasLabel
{
    case Gift = 'gift';
    case OrderHandover = 'order_handover';

    public function getLabel(): string
    {
        return match ($this) {
            self::Gift => __('Gift'),
            self::OrderHandover => __('Order handover'),
        };
    }

    /**
     * @return array<int, string>
     */
    public function getColor(): array
    {
        return match ($this) {
            self::Gift => Palette::uncertain(),
            self::OrderHandover => Palette::positive(),
        };
    }
}
