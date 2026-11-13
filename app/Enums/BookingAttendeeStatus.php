<?php

namespace App\Enums;

use App\Support\Palette;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum BookingAttendeeStatus: string implements HasColor, HasIcon, HasLabel
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
            self::Accepted => __('You said you can help.'),
            self::Tentative => __('You said maybe.'),
            self::Declined => __('You said you cannot make it.'),
            self::NeedsAction => '',
        };
    }

    /**
     * Answers that still need a firm reply from the member. The single definition shared by the invites list, the dashboard, and reminders.
     *
     * @return array<int, BookingAttendeeStatus>
     */
    public static function outstanding(): array
    {
        return [self::NeedsAction, self::Tentative];
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
