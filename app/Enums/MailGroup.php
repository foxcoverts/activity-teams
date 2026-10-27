<?php

namespace App\Enums;

use App\Models\Booking;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum MailGroup: string implements HasIcon, HasLabel
{
    case Bookings = 'bookings';
    case Keys = 'keys';
    case News = 'news';
    case Manager = 'manager';
    case Account = 'account';

    public function getLabel(): string
    {
        return match ($this) {
            self::Bookings => __('Bookings'),
            self::Keys => __('Keys'),
            self::News => __('News'),
            self::Manager => __('Manager'),
            self::Account => __('Account'),
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Bookings => Heroicon::OutlinedCalendarDays,
            self::Keys => Heroicon::OutlinedKey,
            self::News => Heroicon::OutlinedMegaphone,
            self::Manager => Heroicon::OutlinedShieldCheck,
            self::Account => Heroicon::OutlinedUserCircle,
        };
    }

    public function alwaysOn(): bool
    {
        return match ($this) {
            self::Manager, self::Account => true,
            default => false,
        };
    }

    public function hasSubject(): bool
    {
        return $this === self::Bookings;
    }

    public function listUnsubscribe(): bool
    {
        return $this === self::News;
    }

    public function subjectClass(): ?string
    {
        return $this === self::Bookings ? Booking::class : null;
    }

    public function isReleased(): bool
    {
        return match ($this) {
            self::Bookings, self::Keys, self::Manager => true,
            self::News, self::Account => false,
        };
    }

    /**
     * @return list<MailChannel>
     */
    public function channels(): array
    {
        return array_values(array_filter(
            MailChannel::cases(),
            fn (MailChannel $channel): bool => $channel->group() === $this,
        ));
    }

    /**
     * @return list<MailChannel>
     */
    public function shownChannels(): array
    {
        return array_values(array_filter(
            $this->channels(),
            fn (MailChannel $channel): bool => $channel->isShown(),
        ));
    }

    public function masterChannel(): ?MailChannel
    {
        return match ($this) {
            self::Bookings => MailChannel::BookingsMail,
            self::Keys => MailChannel::KeysMail,
            self::News => MailChannel::NewsMail,
            default => null,
        };
    }
}
