<?php

namespace App\Casts;

use App\Enums\BookingAttendeeStatus;
use App\Enums\PastBookingAttendeeStatus;
use App\Models\Booking;
use App\Models\BookingAttendance;
use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<BookingAttendeeStatus|PastBookingAttendeeStatus|null, BookingAttendeeStatus|PastBookingAttendeeStatus|string|null>
 */
class AsBookingAttendeeStatus implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): BookingAttendeeStatus|PastBookingAttendeeStatus|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        $status = BookingAttendeeStatus::from((string) $value);

        if ($model instanceof BookingAttendance && $this->bookingIsPast($model)) {
            return PastBookingAttendeeStatus::from($status->value);
        }

        return $status;
    }

    /**
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [$key => null];
        }

        if (is_string($value)) {
            return [$key => BookingAttendeeStatus::from($value)->value];
        }

        if ($value instanceof BookingAttendeeStatus || $value instanceof PastBookingAttendeeStatus) {
            return [$key => $value->value];
        }

        if ($value instanceof BackedEnum) {
            return [$key => (string) $value->value];
        }

        throw new InvalidArgumentException('The status attribute must be a booking attendee status enum or string.');
    }

    private function bookingIsPast(BookingAttendance $attendance): bool
    {
        if ($attendance->relationLoaded('booking') && $attendance->booking instanceof Booking) {
            return $attendance->booking->isPast();
        }

        if ($attendance->pivotParent instanceof Booking) {
            return $attendance->pivotParent->isPast();
        }

        return false;
    }
}
