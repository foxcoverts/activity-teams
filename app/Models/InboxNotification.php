<?php

namespace App\Models;

use App\Enums\MailChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;

class InboxNotification extends DatabaseNotification
{
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
            'digest_notified_at' => 'datetime',
        ];
    }

    public function markReadForDelivery(): void
    {
        $this->forceFill([
            'read_at' => $this->read_at ?? now(),
            'digest_notified_at' => $this->digest_notified_at ?? now(),
        ])->save();
    }

    public function releaseAfterBounce(): void
    {
        $this->forceFill([
            'read_at' => null,
            'digest_notified_at' => null,
        ])->save();
    }

    public function scopeOutstanding(Builder $query): void
    {
        $query->whereNull('read_at')->whereNull('digest_notified_at');
    }

    public function isAlwaysEmailable(): bool
    {
        return (bool) ($this->data['always_emailable'] ?? false);
    }

    public function isInAppOnly(): bool
    {
        return (bool) ($this->data['in_app_only'] ?? false);
    }

    public function mailChannel(): ?MailChannel
    {
        $value = $this->data['mail_channel'] ?? null;

        return is_string($value) ? MailChannel::tryFrom($value) : null;
    }

    public function preferenceSubject(): ?Booking
    {
        $type = $this->data['subject_type'] ?? null;
        $id = $this->data['subject_id'] ?? null;

        if ($type !== Booking::class || ! filled($id)) {
            return null;
        }

        return Booking::query()->find($id);
    }
}
