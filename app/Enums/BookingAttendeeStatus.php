<?php

namespace App\Enums;

enum BookingAttendeeStatus: string
{
    case Accepted = 'accepted';
    case Tentative = 'tentative';
    case Declined = 'declined';
    case NeedsAction = 'needs-action';

    public function compare(BookingAttendeeStatus $other): int
    {
        return $this->rank() <=> $other->rank();
    }

    protected function rank(): int
    {
        return match ($this) {
            self::Accepted => 0,
            self::Tentative => 1,
            self::Declined => 2,
            self::NeedsAction => 3,
        };
    }
}
