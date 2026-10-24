<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeStatus;
use App\Enums\MembershipRole;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_displayed(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_login_page_does_not_offer_registration(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('sign up for an account')
            ->assertDontSee(route('filament.member.auth.register'), escape: false);
    }

    public function test_team_member_can_open_bookings_list(): void
    {
        $user = User::factory()->teamMember()->create();

        $this->actingAs($user)
            ->get('/bookings')
            ->assertOk();
    }

    public function test_team_manager_sees_manage_link_in_members_sidebar(): void
    {
        $manager = User::factory()->teamLeader()->create();
        $lastManagedTeam = Team::factory()->create(['name' => 'Archery']);
        $lastManagedTeam->grant($manager, MembershipRole::Manager);

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($manager);

        $this->get('/bookings')
            ->assertOk()
            ->assertSeeTextInOrder(['Dashboard', 'Manage'])
            ->assertSee('/manage/'.$lastManagedTeam->getKey(), false);
    }

    public function test_team_member_does_not_see_manage_link_in_members_sidebar(): void
    {
        $member = User::factory()->teamMember()->create();

        $this->actingAs($member)
            ->get('/bookings')
            ->assertOk()
            ->assertDontSee('Manage')
            ->assertDontSee('href="/manage', false);
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

        $team = $leader->managedTeams()->first();

        $this->actingAs($leader)
            ->get('/manage/'.$team->getKey().'/bookings')
            ->assertOk();
    }

    public function test_team_leader_can_open_manage_panel(): void
    {
        $user = User::factory()->teamLeader()->create();
        $team = $user->managedTeams()->first();

        $this->actingAs($user)
            ->get('/manage')
            ->assertRedirect('/manage/'.$team->getKey());
    }

    public function test_manage_sidebar_links_back_to_members_panel(): void
    {
        $manager = User::factory()->teamLeader()->create();
        $team = $manager->managedTeams()->first();
        $membersUrl = Filament::getPanel('member')->getUrl();

        $this->actingAs($manager)
            ->get('/manage/'.$team->getKey())
            ->assertOk()
            ->assertSeeTextInOrder(['Dashboard', 'Members', 'Team admin', 'Site admin'])
            ->assertSee('href="'.$membersUrl.'"', false);
    }
}
