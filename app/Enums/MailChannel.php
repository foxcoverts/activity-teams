<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MailChannel: string implements HasLabel
{
    case BookingsMail = 'bookings.mail';
    case BookingsUpdates = 'bookings.updates';
    case BookingsComments = 'bookings.comments';
    case BookingsDigest = 'bookings.digest';
    case KeysMail = 'keys.mail';
    case NewsMail = 'news.mail';

    public function getLabel(): string
    {
        return match ($this) {
            self::BookingsMail, self::KeysMail, self::NewsMail => __('All mail'),
            self::BookingsUpdates => __('Updates'),
            self::BookingsComments => __('Comments'),
            self::BookingsDigest => __('Digest'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::BookingsMail => __('Invites and attendance recorded by a manager.'),
            self::BookingsUpdates => __('Changes, confirms, cancels, and lead-instructor responsibility.'),
            self::BookingsComments => __('Comments on a booking. Mail is not sent until commenting is available.'),
            self::BookingsDigest => __('A periodic summary of your rota.'),
            self::KeysMail => __('When a key is passed to you, passed on, or returned to the cupboard.'),
            self::NewsMail => __('Direct email when a team publishes news.'),
        };
    }

    public function group(): MailGroup
    {
        return match ($this) {
            self::BookingsMail, self::BookingsUpdates, self::BookingsComments, self::BookingsDigest => MailGroup::Bookings,
            self::KeysMail => MailGroup::Keys,
            self::NewsMail => MailGroup::News,
        };
    }

    public function parent(): ?self
    {
        return match ($this) {
            self::BookingsUpdates, self::BookingsComments, self::BookingsDigest => self::BookingsMail,
            default => null,
        };
    }

    public function default(): bool
    {
        return true;
    }

    public function isShown(): bool
    {
        return true;
    }

    public function formKey(): string
    {
        return str_replace('.', '_', $this->value);
    }
}
