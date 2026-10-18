<?php

namespace App\Enums;

use App\Enums\Concerns\CoversValues;
use Filament\Support\Contracts\HasLabel;

enum GirlguidingLevel: string implements HasLabel
{
    use CoversValues;

    case One = '1';
    case Two = '2';

    public function getLabel(): string
    {
        return match ($this) {
            self::One => __('Level 1'),
            self::Two => __('Level 2'),
        };
    }

    /**
     * @return list<static>
     */
    protected function directlyCovers(): array
    {
        return match ($this) {
            self::Two => [self::One],
            self::One => [],
        };
    }
}
