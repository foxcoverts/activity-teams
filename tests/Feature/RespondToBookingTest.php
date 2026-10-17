<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeStatus;
use App\Filament\Team\Resources\Bookings\BookingResource;
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
            ->assertHasNoErrors()
            ->assertSee('You said you can help.')
            ->assertDontSee('Yes, I can help');

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

    public function test_saved_response_is_shown_until_they_choose_to_change_it(): void
    {
        $booking = Booking::factory()->create();
        $attendee = User::factory()->teamMember()->create();
        $token = BookingAttendance::generateToken();

        $booking->attendees()->attach($attendee->id, [
            'status' => BookingAttendeeStatus::Accepted->value,
            'token' => $token,
        ]);

        Livewire::withQueryParams(['invite' => $token])
            ->test(RespondToBooking::class, [
                'booking' => $booking,
                'attendee' => $attendee,
            ])
            ->assertSee('You said you can help.')
            ->assertSee('Change response')
            ->assertSee('View booking')
            ->assertSee(BookingResource::getUrl('view', ['record' => $booking], panel: 'team'))
            ->assertSeeHtml('bg-sky-50')
            ->assertDontSee('Yes, I can help')
            ->call('changeResponse')
            ->assertSee('Keep this response')
            ->assertDontSee('Yes, I can help')
            ->assertSee('Maybe')
            ->assertSee('I cannot make it')
            ->call('keepResponse')
            ->assertSee('Change response')
            ->assertDontSee('Maybe')
            ->call('changeResponse')
            ->call('respond', 'tentative')
            ->assertSee('You said maybe.')
            ->assertSeeHtml('bg-amber-50')
            ->assertSee('Change response');
    }

    public function test_past_booking_shows_the_saved_response_without_a_way_to_change_it(): void
    {
        $booking = Booking::factory()->create([
            'start_at' => now()->subHours(3),
            'end_at' => now()->subHour(),
        ]);
        $attendee = User::factory()->teamMember()->create();
        $token = BookingAttendance::generateToken();

        $booking->attendees()->attach($attendee->id, [
            'status' => BookingAttendeeStatus::Declined->value,
            'token' => $token,
        ]);

        Livewire::withQueryParams(['invite' => $token])
            ->test(RespondToBooking::class, [
                'booking' => $booking,
                'attendee' => $attendee,
            ])
            ->assertSee('You said you cannot make it.')
            ->assertSee('View booking')
            ->assertSeeHtml('bg-slate-100')
            ->assertDontSee('Change response')
            ->assertDontSee('Yes, I can help');
    }

    public function test_opening_a_choice_link_does_not_save_until_they_confirm(): void
    {
        $booking = Booking::factory()->create();
        $attendee = User::factory()->teamMember()->create();
        $token = BookingAttendance::generateToken();

        $booking->attendees()->attach($attendee->id, [
            'status' => BookingAttendeeStatus::NeedsAction->value,
            'token' => $token,
        ]);

        $this->get(route('respond', [$booking, $attendee, 'invite' => $token, 'choice' => 'accepted']))
            ->assertOk()
            ->assertSee('Confirm now')
            ->assertSee('Cancel')
            ->assertSee('Yes, I can help');

        $this->assertDatabaseHas('booking_user', [
            'booking_id' => $booking->id,
            'user_id' => $attendee->id,
            'status' => BookingAttendeeStatus::NeedsAction->value,
        ]);

        Livewire::withQueryParams(['invite' => $token, 'choice' => 'accepted'])
            ->test(RespondToBooking::class, [
                'booking' => $booking,
                'attendee' => $attendee,
            ])
            ->call('cancelChoice')
            ->assertDontSee('Confirm now')
            ->assertSee('Yes, I can help');

        $this->assertDatabaseHas('booking_user', [
            'booking_id' => $booking->id,
            'user_id' => $attendee->id,
            'status' => BookingAttendeeStatus::NeedsAction->value,
        ]);

        Livewire::withQueryParams(['invite' => $token, 'choice' => 'accepted'])
            ->test(RespondToBooking::class, [
                'booking' => $booking,
                'attendee' => $attendee,
            ])
            ->call('respond', 'accepted')
            ->assertSee('You said you can help.');

        $this->assertDatabaseHas('booking_user', [
            'booking_id' => $booking->id,
            'user_id' => $attendee->id,
            'status' => BookingAttendeeStatus::Accepted->value,
        ]);
    }
}
