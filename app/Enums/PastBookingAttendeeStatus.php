<?php

namespace App\Enums;

use App\Support\Palette;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum PastBookingAttendeeStatus: string implements HasColor, HasIcon, HasLabel
{
    case Accepted = 'accepted';
    case Tentative = 'tentative';
    case Declined = 'declined';
    case NeedsAction = 'needs-action';

    public function getLabel(): string
    {
        return match ($this) {
            self::NeedsAction => __('No response'),
            self::Accepted => __('Helped'),
            self::Tentative => __('Maybe'),
            self::Declined => __('Did not attend'),
        };
    }

    /**
     * @return array<int, string>
     */
    public function getColor(): array
    {
        return match ($this) {
            self::Accepted => Palette::positive(),
            self::Declined => Palette::destructive(),
            self::Tentative => Palette::uncertain(),
            self::NeedsAction => Palette::neutral(),
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Accepted, self::Tentative => Heroicon::CheckCircle,
            self::Declined => Heroicon::XCircle,
            self::NeedsAction => Heroicon::QuestionMarkCircle,
        };
    }

    public function responseSentence(): string
    {
        return match ($this) {
            self::Accepted => __('You said you helped.'),
            self::Tentative => __('You said maybe.'),
            self::Declined => __('You said you did not attend.'),
            self::NeedsAction => '',
        };
    }

    public function isSame(BookingAttendeeStatus|PastBookingAttendeeStatus|string|null $other): bool
    {
        if ($other === null) {
            return false;
        }

        return $this->value === (is_string($other) ? $other : $other->value);
    }

    public function compare(BookingAttendeeStatus|PastBookingAttendeeStatus $other): int
    {
        return $this->rank() <=> $other->rank();
    }

    public function rank(): int
    {
        return match ($this) {
            self::Accepted => 0,
            self::Tentative => 1,
            self::Declined => 2,
            self::NeedsAction => 3,
        };
    }
}
