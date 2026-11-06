<?php

namespace Tests\Feature;

use App\Actions\AddAttendeeAction;
use App\Actions\InviteAttendeesAction;
use App\Enums\BookingAttendeeStatus;
use App\Enums\MembershipRole;
use App\Filament\Admin\Resources\Activities\Pages\CreateActivity;
use App\Filament\Admin\Resources\Teams\Pages\CreateTeam;
use App\Filament\Admin\Resources\Teams\Pages\EditTeam;
use App\Filament\Admin\Resources\Teams\Pages\ListTeams;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Filament\Admin\Resources\Users\Pages\ViewUser;
use App\Filament\Admin\Resources\Users\RelationManagers\MembershipsRelationManager;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Filament\Manage\Resources\Bookings\BookingResource;
use App\Filament\Manage\Resources\Keys\KeyResource;
use App\Filament\Manage\Resources\Members\MemberResource;
use App\Filament\Manage\Resources\Members\Pages\ListMembers;
use App\Filament\Manage\Resources\Members\Pages\ViewMember;
use App\Filament\RelationManagers\QualificationsRelationManager;
use App\Filament\Teams\Resources\Activities\ActivityResource;
use App\Filament\Teams\Resources\Activities\Pages\ListActivities;
use App\Filament\Teams\Resources\Venues\Pages\ListVenues;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\Key;
use App\Models\Lock;
use App\Models\Qualification;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
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

        Filament::setCurrentPanel('manage');
    }

    public function test_a_person_can_belong_to_more_than_one_team(): void
    {
        $climbing = Team::factory()->create();
        $archery = Team::factory()->create();

        $manager = User::factory()->teamManager($climbing)->teamManager($archery)->create();
        $member = User::factory()->teamMember($climbing)->teamMember($archery)->create();
        $managerMember = User::factory()->teamManager($climbing)->teamMember($archery)->create();

        $this->assertTrue($member->isCurrentMemberOf($climbing));
        $this->assertTrue($member->isCurrentMemberOf($archery));
        $this->assertTrue($manager->manages($climbing));
        $this->assertTrue($manager->manages($archery));
        $this->assertTrue($managerMember->manages($climbing));
        $this->assertTrue($managerMember->isCurrentMemberOf($archery));
    }

    public function test_role_on_a_team_returns_the_current_membership_role(): void
    {
        $climbing = Team::factory()->create();
        $archery = Team::factory()->create();
        $user = User::factory()->teamManager($climbing)->teamMember($archery)->create();

        $this->assertSame(MembershipRole::Manager, $user->roleOn($climbing));
        $this->assertSame(MembershipRole::Member, $user->roleOn($archery));
        $this->assertNull($user->roleOn(Team::factory()->create()));

        $user->load('teams');

        $this->assertSame(MembershipRole::Manager, $user->roleOn($climbing));
        $this->assertSame(MembershipRole::Member, $user->roleOn($archery));
    }

    public function test_the_users_list_shows_team_role_when_filtered_by_team(): void
    {
        $climbing = Team::factory()->create();
        $archery = Team::factory()->create();
        $admin = User::factory()->systemAdmin()->teamManager($climbing)->teamMember($archery)->create();
        $member = User::factory()->teamMember($climbing)->create();

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$admin, $member])
            ->assertTableColumnVisible('teams.name')
            ->assertTableColumnHidden('team_role')
            ->filterTable('team', $climbing)
            ->assertTableColumnHidden('teams.name')
            ->assertTableColumnVisible('team_role')
            ->assertTableColumnStateSet('team_role', MembershipRole::Manager, $admin)
            ->assertTableColumnStateSet('team_role', MembershipRole::Member, $member);
    }

    public function test_the_last_manager_cannot_step_down(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $deputy = User::factory()->teamManager($team)->create();

        $team->recordLeft($manager);

        $this->assertFalse($manager->fresh()->manages($team));
        $this->assertTrue($deputy->fresh()->manages($team));

        $this->expectException(ValidationException::class);
        $team->recordLeft($deputy);
    }

    public function test_the_last_manager_is_told_why_they_cannot_step_down(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $member = User::factory()->teamMember($team)->create();
        $membership = $manager->memberships()->first();

        Filament::setCurrentPanel('manage');
        $this->actingAs($manager);

        Livewire::test(ListMembers::class)
            ->assertTableActionDisabled('changeRole', $manager)
            ->assertTableActionDisabled('leave', $manager)
            ->assertTableActionEnabled('changeRole', $member)
            ->assertTableActionEnabled('leave', $member)
            ->assertSee(Team::lastManagerMessage());

        $oversight = User::factory()->oversight()->create();

        Filament::setCurrentPanel('admin');
        $this->actingAs($oversight);

        Livewire::test(MembershipsRelationManager::class, [
            'ownerRecord' => $manager,
            'pageClass' => ViewUser::class,
        ])
            ->assertTableActionDisabled('changeRole', $membership)
            ->assertTableActionDisabled('leave', $membership)
            ->assertSee(Team::lastManagerMessage());

        $team->grant($member, MembershipRole::Manager);

        Filament::setCurrentPanel('manage');
        $this->actingAs($manager);

        Livewire::test(ListMembers::class)
            ->assertTableActionEnabled('changeRole', $manager)
            ->assertTableActionEnabled('leave', $manager)
            ->assertDontSee(Team::lastManagerMessage());
    }

    public function test_a_member_only_sees_their_own_teams_confirmed_bookings(): void
    {
        $climber = User::factory()->teamMember()->create();
        $climbing = $climber->teams()->first();
        $archery = Team::factory()->create();
        $archeryManager = User::factory()->teamManager($archery)->create();
        $archer = User::factory()->teamMember($archery)->create();

        $confirmed = Booking::factory()->forTeam($archery)->confirmed()->create();
        $tentative = Booking::factory()->forTeam($archery)->create();

        $this->assertTrue($archer->can('view', $confirmed));
        $this->assertFalse($archer->can('view', $tentative));
        $this->assertTrue($archeryManager->can('view', $tentative));
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
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $member = User::factory()->teamMember($team)->create();
        $outsider = User::factory()->create();
        $booking = Booking::factory()->forTeam($team)->create();
        $booking->attendees()->attach($outsider->id, [
            'status' => 'needs-action',
            'token' => 'already-on-roster',
        ]);

        (new InviteAttendeesAction)($booking, [$outsider->id, $member->id], $manager);

        $this->assertTrue($booking->attendees()->whereKey($member->getKey())->exists());
        $this->assertSame('already-on-roster', $booking->attendees()->find($outsider)->attendance->token);

        $this->expectException(ValidationException::class);
        (new AddAttendeeAction)($booking, $outsider, $manager, BookingAttendeeStatus::Accepted);
    }

    public function test_leaving_keeps_attendance_and_drops_future_invites(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $member = User::factory()->teamMember($team)->create();
        $booking = Booking::factory()->forTeam($team)->confirmed()->create();
        $booking->attendees()->attach($member->id, [
            'status' => 'accepted',
        ]);

        $team->recordLeft($member);

        $this->assertTrue($member->fresh()->can('view', $booking));
        $this->assertFalse($member->fresh()->isCurrentMemberOf($team));

        (new InviteAttendeesAction)($booking, [$member->id], $manager);

        $this->assertSame(
            'accepted',
            $booking->attendees()->find($member)->attendance->status->value,
        );
    }

    public function test_member_contact_details_are_hidden_from_member_list_and_need_acknowledgement_on_the_member_record(): void
    {
        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->teamMember()->create([
            'email' => 'member@example.test',
            'phone' => '07000111222',
            'emergency_name' => 'Emergency Contact',
            'emergency_phone' => '07000333444',
        ]);
        $oversight = User::factory()->oversight()->create();

        $this->assertTrue($oversight->can('viewAny', User::class));
        $this->assertFalse($member->can('viewAny', User::class));
        $this->assertTrue($manager->can('view', $member));

        $this->actingAs($manager);

        Livewire::test(ListMembers::class)
            ->assertDontSee('member@example.test')
            ->assertDontSee('07000111222')
            ->assertDontSee('Emergency Contact')
            ->assertDontSee('07000333444')
            ->assertSee('Manager')
            ->assertSee('Member')
            ->mountTableAction('changeRole', $member)
            ->assertTableActionDataSet([
                'role' => MembershipRole::Member,
            ]);

        Livewire::test(ViewMember::class, ['record' => $member->getKey()])
            ->assertDontSee('member@example.test')
            ->call('acknowledgePersonalData', 'contact-details')
            ->call('acknowledgePersonalData', 'emergency-contact')
            ->assertSee('member@example.test')
            ->assertSee('+44 70 0011 1222')
            ->assertSee('Emergency Contact')
            ->assertSee('+44 70 0033 3444');

        Livewire::test(ListMembers::class)
            ->assertTableActionDisabled('changeRole', $manager);
    }

    public function test_global_user_directory_hides_contact_details_and_user_record_needs_acknowledgement(): void
    {
        $oversight = User::factory()->oversight()->create();
        $member = User::factory()->create([
            'name' => 'Alex Member',
            'email' => 'member@example.test',
            'phone' => '07000111222',
            'emergency_name' => 'Emergency Contact',
            'emergency_phone' => '07000333444',
        ]);

        Filament::setCurrentPanel('admin');
        $this->actingAs($oversight);

        $this->get(UserResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Alex Member')
            ->assertDontSee('member@example.test')
            ->assertDontSee('07000111222')
            ->assertDontSee('Emergency Contact')
            ->assertDontSee('07000333444');

        Livewire::test(ViewUser::class, ['record' => $member->getKey()])
            ->assertDontSee('member@example.test')
            ->call('acknowledgePersonalData', 'contact-details')
            ->call('acknowledgePersonalData', 'emergency-contact')
            ->assertSee('member@example.test')
            ->assertSee('+44 70 0011 1222')
            ->assertSee('Emergency Contact')
            ->assertSee('+44 70 0033 3444');
    }

    public function test_opening_manage_returns_to_the_last_team(): void
    {
        $first = Team::factory()->create(['name' => 'First']);
        $second = Team::factory()->create(['name' => 'Second']);

        $manager = User::factory()->teamManager($first)->teamManager($second)->create();

        $this->actingAs($manager)
            ->get('/manage/'.$second->slug.'/bookings')
            ->assertOk();

        $this->actingAs($manager)
            ->get('/manage')
            ->assertRedirect('/manage/'.$second->slug);

        $this->assertNotSame($first->getKey(), $second->getKey());
    }

    public function test_manage_navigation_separates_the_team_from_the_site(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();

        $this->actingAs($manager)
            ->get('/manage/'.$team->slug)
            ->assertOk()
            ->assertSeeTextInOrder([
                'Team',
                'Bookings',
            ]);
    }

    public function test_a_manager_does_not_see_site_admin_navigation(): void
    {
        $manager = User::factory()->teamManager()->create();

        $this->actingAs($manager)
            ->get('/manage/'.$manager->managedTeams()->first()->slug)
            ->assertOk()
            ->assertDontSee('Organisation')
            ->assertDontSee('Audiences')
            ->assertDontSee('Locks')
            ->assertDontSee('Inbound mail');
    }

    public function test_system_admin_creates_a_team_and_its_first_manager(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $manager = User::factory()->create();

        Filament::setCurrentPanel('admin');
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
        $this->assertSame('archery', $team->slug);
        $this->assertTrue($manager->fresh()->manages($team));
    }

    public function test_system_admin_can_edit_a_team_slug(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $team = Team::factory()->create();

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(EditTeam::class, ['record' => $team->slug])
            ->fillForm([
                'slug' => 'indoor-climbing',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Team::class, [
            'id' => $team->getKey(),
            'slug' => 'indoor-climbing',
        ]);
    }

    public function test_a_team_slug_must_be_unique(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $team = Team::factory()->create();
        Team::factory()->create(['slug' => 'taken-slug']);

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(EditTeam::class, ['record' => $team->slug])
            ->fillForm([
                'slug' => 'taken-slug',
            ])
            ->call('save')
            ->assertHasFormErrors(['slug' => 'unique']);

        $this->assertDatabaseMissing(Team::class, [
            'id' => $team->getKey(),
            'slug' => 'taken-slug',
        ]);
    }

    public function test_a_team_slug_must_be_url_safe(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $team = Team::factory()->create(['slug' => 'kept-team']);

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(EditTeam::class, ['record' => $team->slug])
            ->fillForm([
                'slug' => 'not/a slug',
            ])
            ->call('save')
            ->assertHasFormErrors(['slug' => 'alpha_dash']);

        $this->assertDatabaseHas(Team::class, [
            'id' => $team->getKey(),
            'slug' => 'kept-team',
        ]);
    }

    public function test_team_catalog_links_to_team_manage_panel(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $team = Team::factory()->create();

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(ListTeams::class)
            ->assertActionHasUrl(
                TestAction::make('manage')->table($team),
                Dashboard::getUrl(panel: 'manage', tenant: $team)
            );
    }

    public function test_system_admin_maintains_the_catalogs_and_reaches_the_user_directory(): void
    {
        $activity = Activity::factory()->create(['name' => 'Unused']);
        $admin = User::factory()->systemAdmin()->create();
        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->teamMember()->create([
            'phone' => '07000999888',
        ]);

        $this->assertTrue($admin->can('create', Activity::class));
        $this->assertTrue($admin->can('update', $activity));
        $this->assertTrue($admin->can('delete', $activity));
        $this->assertTrue($admin->can('create', Venue::class));

        $venue = Venue::factory()->create();

        $this->assertFalse($manager->can('create', Activity::class));
        $this->assertFalse($manager->can('delete', $venue));

        $this->assertTrue($admin->can('viewAny', User::class));
        $this->assertTrue($admin->can('view', $member));
        $this->assertTrue($admin->can('update', $member));
        $this->assertFalse($admin->can('delete', $member));

        $team = Team::factory()->create();
        $team->activities()->syncWithoutDetaching([$activity->getKey()]);

        $this->assertTrue($team->activities()->whereKey($activity->getKey())->exists());

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

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

    public function test_a_system_admin_can_set_up_a_team_they_do_not_manage(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $team = Team::factory()->create(['name' => 'Climbing']);
        $empty = Team::factory()->create(['name' => 'Unstaffed']);
        $manager = User::factory()->teamManager($team)->create(['name' => 'Casey Manager']);
        $member = User::factory()->teamMember($team)->create([
            'name' => 'Alex Member',
            'phone' => '07000999888',
            'emergency_phone' => '07000111222',
        ]);
        $activity = Activity::factory()->create(['name' => 'Scout climbing']);
        $venue = Venue::factory()->create(['name' => 'Campsite']);

        Filament::setCurrentPanel('teams');
        $this->actingAs($admin);

        Livewire::test(ListActivities::class)
            ->assertSuccessful()
            ->callAction('give', data: [
                'activity_id' => $activity->getKey(),
            ]);

        Livewire::test(ListVenues::class)
            ->callAction('give', data: [
                'venue_id' => $venue->getKey(),
            ]);

        $this->assertTrue($team->activities()->whereKey($activity->getKey())->exists());
        $this->assertTrue($team->venues()->whereKey($venue->getKey())->exists());

        $key = Lock::factory()->create();
        $key->requirements()->create([
            'activity_id' => $activity->getKey(),
            'venue_id' => $venue->getKey(),
        ]);

        $keyRing = Key::factory()->create(['name' => 'Climbing keys']);
        $keyRing->locks()->attach($key);

        Filament::setCurrentPanel('manage');

        $this->get(KeyResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Climbing keys');

        Filament::setCurrentPanel('teams');

        Livewire::test(ListActivities::class)
            ->callTableAction('remove', $activity);

        Livewire::test(ListVenues::class)
            ->callTableAction('remove', $venue);

        $this->assertFalse($team->fresh()->activities()->whereKey($activity->getKey())->exists());
        $this->assertFalse($team->fresh()->venues()->whereKey($venue->getKey())->exists());

        Filament::setCurrentPanel('manage');

        $this->get(MemberResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Alex Member')
            ->assertDontSee('07000999888')
            ->assertDontSee('07000111222');

        Livewire::test(ViewMember::class, ['record' => $member->getKey()])
            ->assertDontSee('07000999888')
            ->call('acknowledgePersonalData', 'contact-details')
            ->call('acknowledgePersonalData', 'emergency-contact')
            ->assertSee('+44 70 0099 9888')
            ->assertSee('+44 70 0011 1222');

        $this->assertTrue($admin->can('view', $member));
        $this->assertTrue($admin->can('update', $member));

        Livewire::test(ListMembers::class)
            ->callTableAction('changeRole', $member, data: [
                'role' => MembershipRole::Manager->value,
            ]);

        $this->assertTrue($member->fresh()->manages($team));

        Livewire::test(QualificationsRelationManager::class, [
            'ownerRecord' => $member,
            'pageClass' => ViewMember::class,
        ])->callTableAction('create', data: [
            'kind' => 'scout_permit',
            'group' => 'Climbing and Abseiling',
            'node' => 'Natural Top Rope',
            'permit_type' => 'leadership',
            'expires_on' => '2030-06-15',
        ])->assertHasNoActionErrors();

        $this->assertInstanceOf(Qualification::class, $member->qualifications()->first());

        $booking = Booking::factory()->forTeam($team)->create();
        $booking->attendees()->attach($member->getKey(), [
            'status' => BookingAttendeeStatus::Accepted->value,
        ]);
        $attendance = $booking->attendees()->first()?->attendance;

        $this->get(BookingResource::getUrl('create'))->assertForbidden();
        $this->assertFalse($admin->can('create', Booking::class));
        $this->assertInstanceOf(BookingAttendance::class, $attendance);
        $this->assertFalse($admin->can('update', $attendance));
        $this->assertFalse($admin->can('create', [BookingAttendance::class, $booking]));

        Filament::setCurrentPanel('admin');

        Livewire::test(ListTeams::class)
            ->assertSee('Casey Manager')
            ->assertSee('No managers')
            ->assertSeeHtml('fi-ta-placeholder')
            ->assertSeeHtml(MemberResource::getUrl('index', panel: 'manage', tenant: $team))
            ->assertDontSeeHtml(MemberResource::getUrl('index', panel: 'manage', tenant: $empty));

        $outsideTeam = Team::factory()->create();
        $outsider = User::factory()->teamManager($outsideTeam)->create();

        Filament::setCurrentPanel('manage');
        $this->actingAs($outsider);
        $this->get(MemberResource::getUrl('index', tenant: $team))->assertNotFound();

        Filament::setCurrentPanel('teams');
        $this->get(ActivityResource::getUrl('index', tenant: $team))->assertNotFound();

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);

        Livewire::test(ListActivities::class)
            ->callAction('give', data: [
                'activity_id' => $activity->getKey(),
            ]);

        $this->assertTrue($team->activities()->whereKey($activity->getKey())->exists());
    }
}
