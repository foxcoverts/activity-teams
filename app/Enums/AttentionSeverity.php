<?php

namespace App\Enums;

use App\Support\Palette;

enum AttentionSeverity: int
{
    case Neutral = 0;
    case Positive = 1;
    case Uncertain = 2;
    case Destructive = 3;

    /**
     * @return array<int, string>|string
     */
    public function color(): array|string
    {
        return match ($this) {
            self::Destructive => Palette::destructive(),
            self::Uncertain => Palette::uncertain(),
            self::Positive => Palette::positive(),
            self::Neutral => Palette::neutral(),
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Destructive => 'danger',
            self::Uncertain => 'warning',
            self::Positive => 'success',
            self::Neutral => 'gray',
        };
    }

    public function outranks(self $other): bool
    {
        return $this->value > $other->value;
    }
}
