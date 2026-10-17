<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeStatus;
use App\Livewire\RespondToBooking;
use App\Mail\BookingInviteMail;
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
        $booking = Booking::factory()->create([
            'start_at' => '2026-10-24 08:30:00',
            'end_at' => '2026-10-24 10:30:00',
            'timezone' => 'Europe/London',
        ]);
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
            ->assertSee('Date')
            ->assertSee('Sat 24 Oct 2026')
            ->assertSee('Time')
            ->assertSee('09:30 - 11:30')
            ->assertDontSee('Start')
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

    public function test_overnight_booking_shows_start_and_end(): void
    {
        $booking = Booking::factory()->create([
            'start_at' => '2026-10-24 21:00:00',
            'end_at' => '2026-10-24 23:30:00',
            'timezone' => 'Europe/London',
        ]);
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
            ->assertSee('Start')
            ->assertSee('Sat 24 Oct 2026 22:00')
            ->assertSee('End')
            ->assertSee('Sun 25 Oct 2026 00:30')
            ->assertDontSee('Time');

        $html = (new BookingInviteMail($booking, $attendee))->render();

        $this->assertStringContainsString('Sat 24 Oct 2026 22:00', $html);
        $this->assertStringContainsString('Sun 25 Oct 2026 00:30', $html);
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
