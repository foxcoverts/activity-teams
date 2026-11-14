<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeStatus;
use App\Enums\MembershipRole;
use App\Filament\Member\Resources\Bookings\BookingResource as MemberBookingResource;
use App\Filament\Member\Resources\Bookings\Pages\ListBookings as MemberListBookings;
use App\Filament\Teams\Resources\Bookings\BookingResource as TeamsBookingResource;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Filament\Teams\Resources\Members\Pages\ListMembers;
use App\Models\Booking;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression coverage for US-47 through the panels. Model-level rules live in ActivityTeamsTest.
 */
class TeamIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Team $climbing;

    private Team $archery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->climbing = Team::factory()->create(['name' => 'Climbing']);
        $this->archery = Team::factory()->create(['name' => 'Archery']);
    }

    private function person(Team $team, MembershipRole $role, ?User $user = null): User
    {
        $user ??= User::factory()->create();
        $team->grant($user, $role);

        return $user;
    }

    private function booking(Team $team, string $group, bool $confirmed = true): Booking
    {
        return Booking::factory()
            ->state(['team_id' => $team->getKey(), 'group_name' => $group])
            ->when($confirmed, fn ($factory) => $factory->confirmed())
            ->create();
    }

    public function test_the_teams_panel_lists_only_the_current_teams_bookings(): void
    {
        $manager = $this->person($this->climbing, MembershipRole::Manager);
        $this->person($this->archery, MembershipRole::Manager, $manager);
        Booking::factory()->forTeam($this->climbing)->create([
            'activity_label' => 'Climbing group',
            'activity_id' => null,
        ]);
        Booking::factory()->forTeam($this->archery)->create([
            'activity_label' => 'Archery group',
            'activity_id' => null,
        ]);

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);

        $this->get(TeamsBookingResource::getUrl('index', panel: 'teams', tenant: $this->climbing))
            ->assertOk()
            ->assertSee('Climbing group')
            ->assertDontSee('Archery group');

        $this->get(TeamsBookingResource::getUrl('index', panel: 'teams', tenant: $this->archery))
            ->assertOk()
            ->assertSee('Archery group')
            ->assertDontSee('Climbing group');
    }

    public function test_a_manager_cannot_open_or_change_another_teams_booking(): void
    {
        $manager = $this->person($this->climbing, MembershipRole::Manager);
        $archeryBooking = $this->booking($this->archery, 'Archery group', confirmed: false);
        $cancelled = Booking::factory()->cancelled()->create(['team_id' => $this->archery->getKey()]);

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);

        foreach (['view', 'edit'] as $page) {
            $this->get(TeamsBookingResource::getUrl($page, ['record' => $archeryBooking], panel: 'teams', tenant: $this->climbing))
                ->assertNotFound();
            $this->get(TeamsBookingResource::getUrl($page, ['record' => $archeryBooking], panel: 'teams', tenant: $this->archery))
                ->assertNotFound();
        }

        $this->assertFalse($manager->can('view', $archeryBooking));
        $this->assertFalse($manager->can('update', $archeryBooking));
        $this->assertFalse($manager->can('delete', $cancelled));
    }

    public function test_every_manager_of_a_team_can_manage_its_bookings(): void
    {
        $first = $this->person($this->climbing, MembershipRole::Manager);
        $second = $this->person($this->climbing, MembershipRole::Manager);
        $booking = $this->booking($this->climbing, 'Climbing group', confirmed: false);

        foreach ([$first, $second] as $manager) {
            $this->assertTrue($manager->can('view', $booking));
            $this->assertTrue($manager->can('update', $booking));
        }

        $this->assertSame(2, $this->climbing->managers()->count());
    }

    public function test_a_manager_can_only_open_members_of_the_current_team(): void
    {
        $manager = $this->person($this->climbing, MembershipRole::Manager);
        $climber = $this->person($this->climbing, MembershipRole::Member);
        $archer = $this->person($this->archery, MembershipRole::Member);
        $both = $this->person($this->climbing, MembershipRole::Member);
        $this->person($this->archery, MembershipRole::Member, $both);

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);

        Livewire::test(ListMembers::class)
            ->assertCanSeeTableRecords([$climber, $both])
            ->assertCanNotSeeTableRecords([$archer]);

        $this->get(MemberResource::getUrl('view', ['record' => $climber], panel: 'teams', tenant: $this->climbing))->assertOk();
        $this->get(MemberResource::getUrl('view', ['record' => $archer], panel: 'teams', tenant: $this->climbing))->assertNotFound();
        $this->get(MemberResource::getUrl('index', panel: 'teams', tenant: $this->archery))->assertNotFound();
    }

    public function test_a_member_can_browse_team_bookings_but_cannot_manage_them(): void
    {
        $member = $this->person($this->climbing, MembershipRole::Member);
        $this->person($this->climbing, MembershipRole::Manager);
        $booking = $this->booking($this->climbing, 'Climbing group');

        $this->actingAs($member);

        $this->get(MemberBookingResource::viewUrl($booking))->assertOk();

        $this->get(TeamsBookingResource::getUrl('index', panel: 'teams', tenant: $this->climbing))->assertOk();
        $this->get(TeamsBookingResource::getUrl('view', ['record' => $booking], panel: 'teams', tenant: $this->climbing))->assertOk();
        $this->get(TeamsBookingResource::getUrl('edit', ['record' => $booking], panel: 'teams', tenant: $this->climbing))->assertForbidden();

        $this->get(MemberResource::getUrl('index', panel: 'teams', tenant: $this->climbing))->assertForbidden();
    }

    public function test_the_members_workspace_combines_every_team_a_person_belongs_to(): void
    {
        $person = $this->person($this->climbing, MembershipRole::Member);
        $this->person($this->archery, MembershipRole::Member, $person);
        $climbingConfirmed = $this->booking($this->climbing, 'Climbing confirmed');
        $climbingConfirmed->attendees()->attach($person->getKey(), ['status' => BookingAttendeeStatus::Accepted->value]);
        $archeryConfirmed = $this->booking($this->archery, 'Archery confirmed');
        $archeryConfirmed->attendees()->attach($person->getKey(), ['status' => BookingAttendeeStatus::Accepted->value]);
        $archeryTentative = $this->booking($this->archery, 'Archery tentative', confirmed: false);
        $otherTeam = Team::factory()->create();
        $otherConfirmed = $this->booking($otherTeam, 'Other confirmed');

        Filament::setCurrentPanel('member');
        $this->actingAs($person);

        $this->assertFalse(Filament::getCurrentPanel()->hasTenancy());

        Livewire::test(MemberListBookings::class)
            ->assertCanSeeTableRecords([$climbingConfirmed, $archeryConfirmed])
            ->assertCanNotSeeTableRecords([$archeryTentative, $otherConfirmed]);
    }

    public function test_someone_who_left_the_team_can_still_find_and_open_the_bookings_they_attended(): void
    {
        $leaver = $this->person($this->archery, MembershipRole::Member);
        $this->person($this->archery, MembershipRole::Manager);
        $attended = $this->booking($this->archery, 'Archery attended');
        $other = $this->booking($this->archery, 'Archery other');
        $attended->attendees()->attach($leaver->getKey(), ['status' => BookingAttendeeStatus::Accepted->value]);

        $this->archery->recordLeft($leaver);

        Filament::setCurrentPanel('member');
        $this->actingAs($leaver);

        Livewire::test(MemberListBookings::class)
            ->assertCanSeeTableRecords([$attended])
            ->assertCanNotSeeTableRecords([$other]);

        $this->get(MemberBookingResource::viewUrl($attended))->assertOk();
        $this->get(MemberBookingResource::viewUrl($other))->assertNotFound();
    }
}
