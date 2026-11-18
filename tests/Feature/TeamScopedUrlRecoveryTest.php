<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Filament\Member\Resources\Bookings\BookingResource as MemberBookingResource;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Filament\Teams\Resources\Tasks\TaskResource;
use App\Models\Booking;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamScopedUrlRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_wrong_team_slug_redirects_to_the_booking_owner_team(): void
    {
        $owner = Team::factory()->create(['slug' => 'owner-team']);
        $booking = Booking::factory()->forTeam($owner)->create();
        $wrong = Team::factory()->create(['slug' => 'wrong-team']);

        $this->get('/teams/'.$wrong->slug.'/bookings/'.$booking->getRouteKey())
            ->assertRedirect(MemberBookingResource::viewUrl($booking))
            ->assertStatus(301);

        $this->get(MemberBookingResource::viewUrl($booking))
            ->assertOk()
            ->assertSee(__('Can you attend this event?'));
    }

    public function test_guest_unknown_team_slug_redirects_to_the_booking_owner_team(): void
    {
        $owner = Team::factory()->create(['slug' => 'owner-team']);
        $booking = Booking::factory()->forTeam($owner)->create();

        $this->get('/teams/broken-slug/bookings/'.$booking->getRouteKey())
            ->assertRedirect(MemberBookingResource::viewUrl($booking))
            ->assertStatus(301);
    }

    public function test_authenticated_wrong_team_slug_redirects_to_the_booking_owner_team(): void
    {
        $manager = User::factory()->teamManager()->create();
        $owner = $manager->managedTeams()->firstOrFail();
        $other = Team::factory()->create(['slug' => 'other-managed']);
        $other->grant($manager, MembershipRole::Manager);
        $booking = Booking::factory()->forTeam($owner)->create();

        $this->actingAs($manager)
            ->get(BookingResource::getUrl('view', ['record' => $booking], panel: 'teams', tenant: $other))
            ->assertRedirect(BookingResource::getUrl('view', ['record' => $booking], panel: 'teams', tenant: $owner))
            ->assertStatus(301);
    }

    public function test_authenticated_wrong_team_slug_redirects_to_the_task_owner_team(): void
    {
        $manager = User::factory()->teamManager()->create();
        $owner = $manager->managedTeams()->firstOrFail();
        $other = Team::factory()->create(['slug' => 'other-managed']);
        $other->grant($manager, MembershipRole::Manager);
        $task = Task::factory()->forTeam($owner)->create();

        $this->actingAs($manager)
            ->get(TaskResource::getUrl('view', ['record' => $task], panel: 'teams', tenant: $other))
            ->assertRedirect(TaskResource::getUrl('view', ['record' => $task], panel: 'teams', tenant: $owner))
            ->assertStatus(301);
    }

    public function test_unknown_booking_stays_not_found(): void
    {
        $team = Team::factory()->create();

        $this->get('/teams/'.$team->slug.'/bookings/01invalidbookingulid00000000')
            ->assertNotFound();
    }
}
