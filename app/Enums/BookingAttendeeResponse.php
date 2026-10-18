<?php

namespace App\Enums;

use App\Support\Palette;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum BookingAttendeeResponse: string implements HasColor, HasIcon, HasLabel
{
    case Yes = 'yes';
    case No = 'no';
    case Maybe = 'maybe';

    public function getLabel(): string
    {
        return match ($this) {
            self::Yes => __('Can help'),
            self::No => __('Cannot make it'),
            self::Maybe => __('Maybe'),
        };
    }

    /**
     * @return array<int, string>
     */
    public function getColor(): array
    {
        return match ($this) {
            self::Yes => Palette::positive(),
            self::No => Palette::destructive(),
            self::Maybe => Palette::uncertain(),
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Yes => Heroicon::CheckCircle,
            self::No => Heroicon::XCircle,
            self::Maybe => Heroicon::QuestionMarkCircle,
        };
    }

    public function toStatus(): BookingAttendeeStatus
    {
        return match ($this) {
            self::Yes => BookingAttendeeStatus::Accepted,
            self::No => BookingAttendeeStatus::Declined,
            self::Maybe => BookingAttendeeStatus::Tentative,
        };
    }

    public static function tryFromStatus(BookingAttendeeStatus|string|null $status): ?self
    {
        if (is_string($status)) {
            $status = BookingAttendeeStatus::tryFrom($status);
        }

        return match ($status) {
            BookingAttendeeStatus::Accepted => self::Yes,
            BookingAttendeeStatus::Declined => self::No,
            BookingAttendeeStatus::Tentative => self::Maybe,
            default => null,
        };
    }
}
