<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeStatus;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_displayed(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_team_member_can_open_bookings_list(): void
    {
        $user = User::factory()->teamMember()->create();

        $this->actingAs($user)
            ->get('/bookings')
            ->assertOk();
    }

    public function test_team_member_cannot_open_manage_panel(): void
    {
        $user = User::factory()->teamMember()->create();

        $this->actingAs($user)
            ->get('/manage')
            ->assertForbidden();
    }

    public function test_manage_bookings_index_loads_with_attendees(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $member = User::factory()->teamMember()->create();
        $booking = Booking::factory()->create();
        $booking->attendees()->attach($member->id, [
            'status' => BookingAttendeeStatus::NeedsAction->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        $this->actingAs($leader)
            ->get('/manage/bookings')
            ->assertOk();
    }

    public function test_team_leader_can_open_manage_panel(): void
    {
        $user = User::factory()->teamLeader()->create();

        $this->actingAs($user)
            ->get('/manage')
            ->assertOk();
    }
}
