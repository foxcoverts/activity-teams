<?php

namespace Tests\Feature;

use App\Actions\AddAttendeeAction;
use App\Actions\InviteAttendeesAction;
use App\Enums\BookingAttendeeStatus;
use App\Enums\MembershipRole;
use App\Filament\Manage\Resources\Activities\Pages\CreateActivity;
use App\Filament\Manage\Resources\Members\Pages\ListMembers;
use App\Filament\Manage\Resources\Teams\Pages\CreateTeam;
use App\Filament\Manage\Resources\Users\Pages\ViewUser;
use App\Filament\Manage\Resources\Users\RelationManagers\MembershipsRelationManager;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityTeamsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('manage'));
    }

    public function test_a_person_can_belong_to_more_than_one_team(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $climbing = $leader->managedTeams()->first();
        $member = User::factory()->teamMember()->create();
        $archery = Team::factory()->create();
        $archery->grant($leader, MembershipRole::Manager);
        $archery->grant($member, MembershipRole::Member);

        $this->assertTrue($member->isCurrentMemberOf($climbing));
        $this->assertTrue($member->isCurrentMemberOf($archery));
        $this->assertTrue($leader->manages($climbing));
        $this->assertTrue($leader->manages($archery));
    }

    public function test_the_last_manager_cannot_step_down(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $team = $leader->managedTeams()->first();
        $deputy = User::factory()->manager()->create();

        $team->recordLeft($leader);

        $this->assertFalse($leader->fresh()->manages($team));
        $this->assertTrue($deputy->fresh()->manages($team));

        $this->expectException(ValidationException::class);
        $team->recordLeft($deputy);
    }

    public function test_the_last_manager_is_told_why_they_cannot_step_down(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $member = User::factory()->teamMember()->create();
        $team = $leader->managedTeams()->first();
        $membership = $leader->memberships()->first();

        $this->actingAs($leader);

        Livewire::test(ListMembers::class)
            ->assertTableActionDisabled('changeRole', $leader)
            ->assertTableActionDisabled('leave', $leader)
            ->assertTableActionEnabled('changeRole', $member)
            ->assertTableActionEnabled('leave', $member)
            ->assertSee(Team::lastManagerMessage());

        $oversight = User::factory()->oversight()->create();

        $this->actingAs($oversight);

        Livewire::test(MembershipsRelationManager::class, [
            'ownerRecord' => $leader,
            'pageClass' => ViewUser::class,
        ])
            ->assertTableActionDisabled('changeRole', $membership)
            ->assertTableActionDisabled('leave', $membership)
            ->assertSee(Team::lastManagerMessage());

        $team->grant($member, MembershipRole::Manager);

        $this->actingAs($leader);

        Livewire::test(ListMembers::class)
            ->assertTableActionEnabled('changeRole', $leader)
            ->assertTableActionEnabled('leave', $leader)
            ->assertDontSee(Team::lastManagerMessage());
    }

    public function test_a_member_only_sees_their_own_teams_confirmed_bookings(): void
    {
        $climber = User::factory()->teamMember()->create();
        $climbing = $climber->teams()->first();
        $archerLeader = User::factory()->teamLeader()->create();
        $archer = User::factory()->teamMember()->create();
        $confirmed = Booking::factory()->confirmed()->create();
        $tentative = Booking::factory()->create();

        $this->assertTrue($archer->can('view', $confirmed));
        $this->assertFalse($archer->can('view', $tentative));
        $this->assertTrue($archerLeader->can('view', $tentative));
        $this->assertFalse($climber->can('view', $confirmed));
        $this->assertFalse(
            Booking::query()->forUser($climber)->whereKey($confirmed->getKey())->exists()
        );
        $this->assertTrue(
            Booking::query()->forUser($archer)->whereKey($confirmed->getKey())->exists()
        );
        $this->assertFalse($climbing->is($confirmed->team));
    }

    public function test_invite_and_add_only_offer_current_members(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $member = User::factory()->teamMember()->create();
        $outsider = User::factory()->create();
        $booking = Booking::factory()->create();
        $booking->attendees()->attach($outsider->id, [
            'status' => 'needs-action',
            'token' => 'already-on-roster',
        ]);

        (new InviteAttendeesAction)($booking, [$outsider->id, $member->id], $leader);

        $this->assertTrue($booking->attendees()->whereKey($member->getKey())->exists());
        $this->assertSame('already-on-roster', $booking->attendees()->find($outsider)->attendance->token);

        $this->expectException(ValidationException::class);
        (new AddAttendeeAction)($booking, $outsider, $leader, BookingAttendeeStatus::Accepted);
    }

    public function test_leaving_keeps_attendance_and_drops_future_invites(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $member = User::factory()->teamMember()->create();
        $team = $leader->managedTeams()->first();
        $booking = Booking::factory()->confirmed()->create();
        $booking->attendees()->attach($member->id, [
            'status' => 'accepted',
        ]);

        $team->recordLeft($member);

        $this->assertTrue($member->fresh()->can('view', $booking));
        $this->assertFalse($member->fresh()->isCurrentMemberOf($team));

        (new InviteAttendeesAction)($booking, [$member->id], $leader);

        $this->assertSame(
            'accepted',
            $booking->attendees()->find($member)->attendance->status->value,
        );
    }

    public function test_oversight_sees_the_directory_and_a_manager_sees_member_contact_details(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $member = User::factory()->teamMember()->create([
            'phone' => '07000111222',
            'emergency_phone' => '07000333444',
        ]);
        $oversight = User::factory()->oversight()->create();

        $this->assertTrue($oversight->can('viewAny', User::class));
        $this->assertFalse($leader->can('viewAny', User::class));
        $this->assertTrue($leader->can('view', $member));

        $this->actingAs($leader);

        Livewire::test(ListMembers::class)
            ->assertSee('07000111222')
            ->assertSee('07000333444')
            ->assertSee('Manager')
            ->assertSee('Member')
            ->mountTableAction('changeRole', $member)
            ->assertTableActionDataSet([
                'role' => MembershipRole::Member,
            ]);

        Livewire::test(ListMembers::class)
            ->mountTableAction('changeRole', $leader)
            ->assertTableActionDataSet([
                'role' => MembershipRole::Manager,
            ]);
    }

    public function test_opening_manage_returns_to_the_last_team(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $first = $leader->managedTeams()->first();
        $second = Team::factory()->create(['name' => 'Archery']);
        $second->grant($leader, MembershipRole::Manager);

        $this->actingAs($leader)
            ->get('/manage/'.$second->getKey().'/bookings')
            ->assertOk();

        $this->actingAs($leader)
            ->get('/manage')
            ->assertRedirect('/manage/'.$second->getKey());

        $this->assertNotSame($first->getKey(), $second->getKey());
    }

    public function test_manage_navigation_separates_the_team_from_the_site(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $team = $leader->managedTeams()->first();

        $this->actingAs($leader)
            ->get('/manage/'.$team->getKey())
            ->assertOk()
            ->assertSeeTextInOrder([
                'Team admin',
                'Bookings',
                'Activities',
                'Venues',
                'Site admin',
                'Teams',
                'Activity catalog',
                'Venue catalog',
            ]);
    }

    public function test_a_manager_does_not_see_site_admin_navigation(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->get('/manage/'.$manager->managedTeams()->first()->getKey())
            ->assertOk()
            ->assertSee('Team admin')
            ->assertSee('Activities')
            ->assertDontSee('Site admin')
            ->assertDontSee('Activity catalog');
    }

    public function test_system_admin_creates_a_team_and_its_first_manager(): void
    {
        $admin = User::factory()->teamLeader()->create();
        $manager = User::factory()->create();

        $this->actingAs($admin);

        Livewire::test(CreateTeam::class)
            ->fillForm([
                'name' => 'Archery',
                'manager_id' => $manager->getKey(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $team = Team::query()->where('name', 'Archery')->first();

        $this->assertNotNull($team);
        $this->assertTrue($manager->fresh()->manages($team));
    }

    public function test_a_new_team_requires_a_first_manager(): void
    {
        $admin = User::factory()->teamLeader()->create();

        $this->actingAs($admin);

        Livewire::test(CreateTeam::class)
            ->fillForm([
                'name' => 'Archery',
            ])
            ->call('create')
            ->assertHasFormErrors(['manager_id' => 'required']);
    }

    public function test_system_admin_maintains_the_catalogs_and_the_flag_hides_personal_data(): void
    {
        $activity = Activity::factory()->create(['name' => 'Unused']);
        $admin = User::factory()->create(['is_system_admin' => true]);
        $leader = User::factory()->teamLeader()->create();
        $member = User::factory()->teamMember()->create([
            'phone' => '07000999888',
        ]);

        $this->assertTrue($admin->can('create', Activity::class));
        $this->assertTrue($admin->can('update', $activity));
        $this->assertTrue($admin->can('delete', $activity));
        $this->assertTrue($admin->can('create', Venue::class));
        $manager = User::factory()->manager()->create();
        $venue = Venue::factory()->create();

        $this->assertFalse($manager->can('create', Activity::class));
        $this->assertFalse($manager->can('delete', $venue));
        $this->assertFalse($admin->can('view', $member));
        $this->assertFalse($admin->can('viewAny', User::class));

        $team = Team::factory()->create();
        $team->grant($admin, MembershipRole::Manager);
        $team->activities()->syncWithoutDetaching([$activity->getKey()]);

        $this->assertTrue($team->activities()->whereKey($activity->getKey())->exists());

        $home = $admin->managedTeams()->first();
        $this->actingAs($admin);
        Filament::setTenant($home);

        Livewire::test(CreateActivity::class)
            ->fillForm([
                'name' => 'Archery',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Activity::class, [
            'name' => 'Archery',
        ]);
    }
}
