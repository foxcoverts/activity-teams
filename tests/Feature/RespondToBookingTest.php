<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeStatus;
use App\Livewire\RespondToBooking;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RespondToBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendee_can_accept_via_token_link(): void
    {
        $booking = Booking::factory()->create();
        $attendee = User::factory()->teamMember()->create();
        $token = BookingAttendance::generateToken();

        $booking->attendees()->attach($attendee->id, [
            'status' => BookingAttendeeStatus::NeedsAction->value,
            'token' => $token,
        ]);

        Livewire::withQueryParams(['invite' => $token])
            ->test(RespondToBooking::class, [
                'booking' => $booking,
                'attendee' => $attendee,
            ])
            ->call('respond', 'accepted')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('booking_user', [
            'booking_id' => $booking->id,
            'user_id' => $attendee->id,
            'status' => BookingAttendeeStatus::Accepted->value,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'event' => 'attendance_updated',
        ]);
    }

    public function test_invalid_token_is_forbidden(): void
    {
        $booking = Booking::factory()->create();
        $attendee = User::factory()->teamMember()->create();
        $booking->attendees()->attach($attendee->id, [
            'status' => BookingAttendeeStatus::NeedsAction->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        $this->get(route('respond', [$booking, $attendee, 'invite' => 'nope']))
            ->assertForbidden();
    }
}
