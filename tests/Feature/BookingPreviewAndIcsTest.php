<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingPreviewAndIcsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_preview_is_available_without_login(): void
    {
        $booking = Booking::factory()->create([
            'group_name' => 'Public Preview Group',
        ]);

        $this->get(route('booking.preview', $booking))
            ->assertOk()
            ->assertSee('Public Preview Group')
            ->assertSee($booking->activity->name)
            ->assertSee($booking->venue->name);
    }

    public function test_ics_feed_requires_token_and_returns_calendar(): void
    {
        $user = User::factory()->teamMember()->create([
            'ical_token' => User::generateToken(),
        ]);
        $booking = Booking::factory()->confirmed()->create();
        $booking->attendees()->attach($user->id, [
            'status' => BookingAttendeeStatus::Accepted->value,
        ]);

        $this->get(route('booking.ics', $user))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertSee('BEGIN:VCALENDAR', false);
    }
}
