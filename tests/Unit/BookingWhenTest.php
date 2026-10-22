<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\User;
use App\Support\BookingWhen;
use Tests\TestCase;

class BookingWhenTest extends TestCase
{
    public function test_same_day_booking_shows_local_times_and_duration(): void
    {
        $booking = new Booking([
            'start_at' => '2026-10-24 10:30:00',
            'end_at' => '2026-10-24 12:30:00',
            'timezone' => 'Europe/London',
        ]);

        $this->assertSame('Sat 24 Oct 2026, 11:30 to 13:30 (2 hours)', BookingWhen::format($booking));
    }

    public function test_multi_day_booking_shows_both_dates_and_duration(): void
    {
        $booking = new Booking([
            'start_at' => '2026-10-24 23:30:00',
            'end_at' => '2026-10-25 00:30:00',
            'timezone' => 'UTC',
        ]);

        $this->assertSame(
            'Sat 24 Oct 2026 23:30 to Sun 25 Oct 2026 00:30 (1 hour)',
            BookingWhen::format($booking),
        );
    }

    public function test_same_local_date_uses_times_when_booking_crosses_utc_midnight(): void
    {
        $booking = new Booking([
            'start_at' => '2026-10-24 23:30:00',
            'end_at' => '2026-10-25 00:30:00',
            'timezone' => 'UTC',
        ]);

        $this->actingAs(new User(['timezone' => 'America/Los_Angeles']));

        $this->assertSame('Sat 24 Oct 2026, 16:30 to 17:30 (1 hour)', BookingWhen::format($booking));
    }
}
