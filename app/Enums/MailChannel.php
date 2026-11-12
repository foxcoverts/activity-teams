<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MailChannel: string implements HasLabel
{
    case BookingsMail = 'bookings.mail';
    case BookingsUpdates = 'bookings.updates';
    case BookingsComments = 'bookings.comments';
    case BookingsReminders = 'bookings.reminders';
    case BookingsDigest = 'bookings.digest';
    case KeysMail = 'keys.mail';
    case NewsMail = 'news.mail';
    case TasksMail = 'tasks.mail';
    case TasksUpdates = 'tasks.updates';

    public function getLabel(): string
    {
        return match ($this) {
            self::BookingsMail, self::KeysMail, self::NewsMail, self::TasksMail => __('All mail'),
            self::BookingsUpdates, self::TasksUpdates => __('Updates'),
            self::BookingsComments => __('Comments'),
            self::BookingsReminders => __('Reminders'),
            self::BookingsDigest => __('Digest'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::BookingsMail => __('Invites and attendance recorded by a manager.'),
            self::BookingsUpdates => __('Changes, confirms, cancels, and lead-instructor responsibility.'),
            self::BookingsComments => __('Comments on a booking.'),
            self::BookingsReminders => __('Reminders before sessions you said you can help with.'),
            self::BookingsDigest => __('A periodic summary of your rota.'),
            self::KeysMail => __('When a key is passed to you, passed on, or returned to the cupboard.'),
            self::NewsMail => __('Direct email when a team publishes news.'),
            self::TasksMail => __('When you are assigned a task.'),
            self::TasksUpdates => __('Changes and cancellations for tasks assigned to you.'),
        };
    }

    public function group(): MailGroup
    {
        return match ($this) {
            self::BookingsMail, self::BookingsUpdates, self::BookingsComments, self::BookingsReminders, self::BookingsDigest => MailGroup::Bookings,
            self::KeysMail => MailGroup::Keys,
            self::NewsMail => MailGroup::News,
            self::TasksMail, self::TasksUpdates => MailGroup::Tasks,
        };
    }

    public function parent(): ?self
    {
        return match ($this) {
            self::BookingsUpdates, self::BookingsComments, self::BookingsReminders, self::BookingsDigest => self::BookingsMail,
            self::TasksUpdates => self::TasksMail,
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
