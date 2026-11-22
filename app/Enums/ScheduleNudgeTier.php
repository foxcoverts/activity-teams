<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum ScheduleNudgeTier: string
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

    /**
     * Widest / highest notification window on the ladder.
     */
    public static function max(): self
    {
        return self::ladder()[0];
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

    /**
     * Ladder band for a future start (null when outside the max horizon or already started).
     */
    public static function forStartAt(CarbonInterface $startAt, ?CarbonInterface $now = null): ?self
    {
        $now ??= now();

        if ($startAt->lte($now)) {
            return null;
        }

        $ladder = self::ladder();

        foreach ($ladder as $index => $tier) {
            $upper = $tier->days();
            $lower = ($ladder[$index + 1] ?? null)?->days() ?? 0;

            if ($startAt->gt($now->copy()->addDays($upper))) {
                continue;
            }

            if ($lower > 0 && $startAt->lte($now->copy()->addDays($lower))) {
                continue;
            }

            return $tier;
        }

        return null;
    }

    /**
     * Next unmet tier due for a start time, honouring half-life on $clock.
     */
    public static function due(
        ?self $alreadySent,
        CarbonInterface $startAt,
        CarbonInterface $clock,
        ?CarbonInterface $now = null,
    ): ?self {
        $now ??= now();

        if ($startAt->lte($now)) {
            return null;
        }

        $ladder = self::ladder();

        foreach ($ladder as $index => $tier) {
            if (! $tier->isAfter($alreadySent)) {
                continue;
            }

            $upper = $tier->days();
            $lower = ($ladder[$index + 1] ?? null)?->days() ?? 0;

            if ($startAt->gt($now->copy()->addDays($upper))) {
                continue;
            }

            if ($lower > 0 && $startAt->lte($now->copy()->addDays($lower))) {
                continue;
            }

            if ($clock->gt($now->copy()->subDays($tier->minAgeDays()))) {
                continue;
            }

            return $tier;
        }

        return null;
    }
}
