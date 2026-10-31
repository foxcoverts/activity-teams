<?php

namespace App\Support;

use App\Enums\BookingAttendeeStatus;
use App\Models\User;
use Sabre\VObject\Property;

class InboundCalendarAttendee
{
    public function __construct(protected Property $vattendee) {}

    public function email(): string
    {
        return strtolower(str_replace('mailto:', '', (string) $this->vattendee->getValue()));
    }

    public function user(): ?User
    {
        return User::query()->where('email', $this->email())->first();
    }

    public function status(): ?BookingAttendeeStatus
    {
        return match (strtoupper((string) ($this->vattendee['PARTSTAT'] ?? ''))) {
            'ACCEPTED' => BookingAttendeeStatus::Accepted,
            'DECLINED' => BookingAttendeeStatus::Declined,
            'NEEDS-ACTION' => BookingAttendeeStatus::NeedsAction,
            'TENTATIVE' => BookingAttendeeStatus::Tentative,
            default => null,
        };
    }
}
