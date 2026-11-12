<?php

namespace App\Enums;

enum InvitationNudgeTier: string
{
    case TwoMonths = 'two_months';
    case Month = 'month';
    case Fortnight = 'fortnight';
    case Week = 'week';

    /**
     * Widest first (56 → 7 days).
     *
     * @return list<self>
     */
    public static function ladder(): array
    {
        return [
            self::TwoMonths,
            self::Month,
            self::Fortnight,
            self::Week,
        ];
    }

    public function days(): int
    {
        return match ($this) {
            self::TwoMonths => 7 * 8,
            self::Month => 7 * 4,
            self::Fortnight => 7 * 2,
            self::Week => 7 * 1,
        };
    }

    public function minAgeDays(): int
    {
        return intdiv($this->days(), 2);
    }

    public function rank(): int
    {
        return match ($this) {
            self::TwoMonths => 1,
            self::Month => 2,
            self::Fortnight => 3,
            self::Week => 4,
        };
    }

    public function isAfter(?self $other): bool
    {
        if ($other === null) {
            return true;
        }

        return $this->rank() > $other->rank();
    }
}
