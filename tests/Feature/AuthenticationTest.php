<?php

namespace Tests\Feature;

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

    public function test_team_leader_can_open_manage_panel(): void
    {
        $user = User::factory()->teamLeader()->create();

        $this->actingAs($user)
            ->get('/manage')
            ->assertOk();
    }
}
