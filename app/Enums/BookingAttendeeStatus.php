<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BookingAttendeeStatus: string implements HasLabel
{
    case Accepted = 'accepted';
    case Tentative = 'tentative';
    case Declined = 'declined';
    case NeedsAction = 'needs-action';

    public function getLabel(): string
    {
        return match ($this) {
            self::NeedsAction => __('Awaiting response'),
            self::Accepted => __('Can help'),
            self::Tentative => __('Maybe'),
            self::Declined => __('Cannot make it'),
        };
    }

    public function responseSentence(): string
    {
        return match ($this) {
            self::Accepted => __('You said you can help.'),
            self::Tentative => __('You said maybe.'),
            self::Declined => __('You said you cannot make it.'),
            self::NeedsAction => '',
        };
    }

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
