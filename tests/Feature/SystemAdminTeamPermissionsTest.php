<?php

namespace Tests\Feature;

use App\Actions\CompleteDebriefAction;
use App\Actions\ConfirmAttendanceAction;
use App\Enums\BookingAttendeeStatus;
use App\Enums\MembershipRole;
use App\Enums\TeamFeature;
use App\Filament\Bookings\CompleteDebriefAction as CompleteDebriefFilamentAction;
use App\Filament\Bookings\ConfirmAttendeesAction;
use App\Filament\Member\Pages\Calendar as MemberCalendar;
use App\Filament\Member\Resources\Tasks\TaskResource as MemberTaskResource;
use App\Filament\Teams\Pages\Calendar as TeamsCalendar;
use App\Filament\Teams\Pages\ManagerDashboard;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Filament\Teams\Resources\EquipmentSets\EquipmentSetResource;
use App\Filament\Teams\Resources\EquipmentSets\Pages\ManageEquipmentSets;
use App\Filament\Teams\Resources\Tasks\TaskResource as TeamsTaskResource;
use App\Filament\Teams\Widgets\Manager\NeedsLeadWidget;
use App\Filament\Teams\Widgets\Manager\OutstandingDebriefsWidget;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Support\ManagerAttention;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SystemAdminTeamPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $manager;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->teamManager()->create();
        $this->team = $this->manager->managedTeams()->firstOrFail();
        $this->admin = User::factory()->systemAdmin()->create();

        $this->team->enableFeature(TeamFeature::Debrief, now()->subDay());
        $this->team->save();

        Filament::setCurrentPanel('teams');
    }

    public function test_system_admin_who_is_not_a_manager_can_manage_equipment_sets(): void
    {
        $this->assertFalse($this->admin->manages($this->team));

        $this->actingAs($this->admin);
        Filament::setTenant($this->team);

        $this->assertTrue(EquipmentSetResource::canViewAny());

        Livewire::test(ManageEquipmentSets::class)->assertSuccessful();
    }

    public function test_system_admin_who_is_not_a_manager_cannot_perform_manager_only_booking_actions(): void
    {
        $booking = Booking::factory()->forTeam($this->team)->confirmed()->create([
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
        ]);
        $member = User::factory()->teamMember($this->team)->create();
        $booking->attendees()->attach($member->getKey(), [
            'status' => BookingAttendeeStatus::Accepted->value,
        ]);
        $attendance = $booking->attendees()->find($member)->attendance;
        assert($attendance instanceof BookingAttendance);

        $this->actingAs($this->admin);
        Filament::setTenant($this->team);

        $this->assertFalse($this->admin->manages($this->team));
        $this->assertFalse($this->admin->can('create', Booking::class));
        $this->assertFalse($this->admin->can('update', $booking));
        $this->assertFalse($this->admin->can('update', $attendance));
        $this->assertFalse($this->admin->can('create', [BookingAttendance::class, $booking]));
        $this->assertFalse($this->admin->can('override', $attendance));

        $this->assertFalse(ConfirmAttendeesAction::isAvailable($booking, $this->admin));
        $this->assertFalse(CompleteDebriefFilamentAction::isAvailable($booking, $this->admin));

        try {
            app(ConfirmAttendanceAction::class)($booking, $this->admin, [
                (string) $member->getKey() => ConfirmAttendanceAction::MARK_CONFIRM,
            ]);
            $this->fail('System admin confirmed attendance without managing the team.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('marks', $exception->errors());
        }

        try {
            app(CompleteDebriefAction::class)($booking, $this->admin, [
                'hours' => 2,
                'participant_count' => 4,
                'weather' => 'Dry',
                'notes' => null,
                'equipment_set_ids' => [],
                'marks' => [
                    (string) $member->getKey() => ConfirmAttendanceAction::MARK_CONFIRM,
                ],
            ]);
            $this->fail('System admin completed a debrief without managing the team.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('debrief', $exception->errors());
        }

        $this->get(BookingResource::getUrl('create', tenant: $this->team))->assertForbidden();
    }

    public function test_system_admin_opens_manager_dashboard_for_bounces_but_not_booking_triage(): void
    {
        $this->actingAs($this->admin);
        Filament::setTenant($this->team);

        $this->assertTrue(ManagerDashboard::canAccess());
        $this->get(ManagerDashboard::getUrl(tenant: $this->team))->assertOk();

        $attention = ManagerAttention::for($this->team, $this->admin);
        $this->assertFalse($attention->includesBookingTriage());
        $this->assertFalse(NeedsLeadWidget::canView());
        $this->assertFalse(OutstandingDebriefsWidget::canView());
    }

    public function test_system_admin_without_membership_does_not_see_bookings_calendar_or_tasks_for_the_team(): void
    {
        $this->team->forceFill(['features' => [TeamFeature::Tasks->value => true]])->save();
        $task = Task::factory()->forTeam($this->team)->create();

        $this->actingAs($this->admin);
        Filament::setTenant($this->team);

        $this->assertFalse($this->admin->isCurrentMemberOf($this->team));
        $this->assertFalse($this->admin->can('viewAny', Booking::class));
        $this->assertFalse(BookingResource::canViewAny());
        $this->assertFalse(TeamsCalendar::canAccess());
        $this->assertFalse($this->admin->can('viewAny', Task::class));
        $this->assertFalse($this->admin->can('view', $task));
        $this->assertFalse(TeamsTaskResource::canViewAny());

        $this->get(BookingResource::getUrl(tenant: $this->team))->assertForbidden();
        $this->get(TeamsCalendar::getUrl(tenant: $this->team))->assertForbidden();
        $this->get(TeamsTaskResource::getUrl(tenant: $this->team))->assertForbidden();

        Filament::setCurrentPanel('member');
        Filament::setTenant(null);

        $this->assertFalse($this->admin->can('viewAny', Booking::class));
        $this->assertFalse(MemberCalendar::canAccess());
        $this->assertFalse($this->admin->can('viewAny', Task::class));
        $this->assertFalse(MemberTaskResource::canViewAny());
    }

    public function test_system_admin_who_belongs_to_another_team_still_cannot_see_this_teams_schedule(): void
    {
        $other = Team::factory()->create();
        $other->grant($this->admin, MembershipRole::Member);
        $this->team->forceFill(['features' => [TeamFeature::Tasks->value => true]])->save();

        $this->actingAs($this->admin);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($this->team);

        $this->assertTrue($this->admin->isCurrentMemberOf($other));
        $this->assertFalse($this->admin->isCurrentMemberOf($this->team));
        $this->assertFalse($this->admin->can('viewAny', Booking::class));
        $this->assertFalse(TeamsCalendar::canAccess());
        $this->assertFalse($this->admin->can('viewAny', Task::class));
    }

    public function test_manager_without_system_admin_keeps_booking_and_debrief_powers(): void
    {
        $booking = Booking::factory()->forTeam($this->team)->confirmed()->create([
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
        ]);
        $member = User::factory()->teamMember($this->team)->create();
        $booking->attendees()->attach($member->getKey(), [
            'status' => BookingAttendeeStatus::Accepted->value,
        ]);

        $this->actingAs($this->manager);
        Filament::setTenant($this->team);

        $this->assertTrue($this->manager->manages($this->team));
        $this->assertFalse($this->manager->isSystemAdmin());
        $this->assertTrue($this->manager->can('update', $booking));
        $this->assertTrue(CompleteDebriefFilamentAction::isAvailable($booking, $this->manager));
        $this->assertFalse(ConfirmAttendeesAction::isAvailable($booking, $this->manager));
        $this->assertTrue(ManagerDashboard::canAccess());
        $this->assertTrue(NeedsLeadWidget::canView());
    }
}
