<?php

namespace App\Enums;

use App\Enums\Concerns\CoversValues;
use Filament\Support\Contracts\HasLabel;

enum GirlguidingScheme: string implements HasLabel
{
    use CoversValues;

    case Climbing = 'climbing';

    public function getLabel(): string
    {
        return match ($this) {
            self::Climbing => __('Climbing'),
        };
    }
}
