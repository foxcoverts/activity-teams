<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TeamFeature;
use App\Filament\Teams\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Teams\Resources\Bookings\Schemas\BookingForm;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Filament\Teams\Resources\Tasks\Pages\CreateTask;
use App\Filament\Teams\Resources\Tasks\Pages\EditTask;
use App\Filament\Teams\Resources\Tasks\Pages\ListTasks;
use App\Filament\Teams\Resources\Tasks\Pages\ViewTask;
use App\Filament\Teams\Resources\Tasks\TaskResource;
use App\Livewire\BookingTasks;
use App\Livewire\TaskRelatedBookings;
use App\Models\Booking;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use App\Support\TaskIcsFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('teams');
    }

    public function test_tasks_nav_and_list_are_hidden_when_the_team_feature_is_off(): void
    {
        $team = Team::factory()->create();
        $manager = User::factory()->teamManager($team)->create();

        $this->actingAs($manager);
        Filament::setTenant($team);

        $this->get(TaskResource::getUrl('index', tenant: $team))->assertForbidden();
        $this->assertFalse(TaskResource::shouldRegisterNavigation());
    }

    public function test_managers_can_create_and_members_can_complete_tasks_when_enabled(): void
    {
        $team = Team::factory()->withTasks()->create();
        $manager = User::factory()->teamManager($team)->create();
        $member = User::factory()->teamMember($team)->create();

        $this->actingAs($manager);
        Filament::setTenant($team);

        $venue = Venue::factory()->create();
        $team->addVenue($venue);

        Livewire::test(CreateTask::class)
            ->fillForm([
                'summary' => 'Check bolts',
                'priority' => 1,
                'venue_id' => $venue->getKey(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $task = Task::query()->where('summary', 'Check bolts')->first();
        $this->assertNotNull($task);
        $this->assertSame($team->getKey(), $task->team_id);
        $this->assertSame($venue->getKey(), $task->venue_id);
        $this->assertSame(TaskStatus::NeedsAction, $task->status);

        $this->actingAs($member);
        Filament::setTenant($team);

        Livewire::test(ListTasks::class)
            ->assertCanSeeTableRecords([$task])
            ->call('toggleTaskComplete', $task->getKey())
            ->assertSuccessful();

        $this->assertTrue($task->fresh()->isComplete());
        $this->assertSame(TaskStatus::Completed, $task->fresh()->status);
    }

    public function test_booking_tasks_panel_supports_add_new_existing_and_remove(): void
    {
        $team = Team::factory()->withTasks()->create();
        $manager = User::factory()->teamManager($team)->create();
        $booking = Booking::factory()->forTeam($team)->create();
        $existing = Task::factory()->forTeam($team)->create(['summary' => 'Existing task']);

        $this->actingAs($manager);

        Livewire::test(BookingTasks::class, ['record' => $booking])
            ->assertSee('Tasks')
            ->callAction('addExisting', data: ['task_id' => $existing->getKey()])
            ->assertNotified();

        $this->assertTrue($booking->fresh()->tasks()->whereKey($existing)->exists());

        Livewire::test(BookingTasks::class, ['record' => $booking->fresh()])
            ->callAction('addNew', data: [
                'summary' => 'Brand new task',
                'priority' => 5,
                'venue_id' => $booking->venue_id,
            ])
            ->assertNotified();

        $created = Task::query()->where('summary', 'Brand new task')->first();
        $this->assertNotNull($created);
        $this->assertSame($booking->venue_id, $created->venue_id);
        $this->assertTrue($booking->fresh()->tasks()->whereKey($created)->exists());

        Livewire::test(BookingTasks::class, ['record' => $booking->fresh()])
            ->call('remove', $existing->getKey())
            ->assertSuccessful();

        $this->assertFalse($booking->fresh()->tasks()->whereKey($existing)->exists());
    }

    public function test_managers_can_create_a_task_at_a_private_other_venue(): void
    {
        $team = Team::factory()->withTasks()->create();
        $manager = User::factory()->teamManager($team)->create();

        $this->actingAs($manager);
        Filament::setTenant($team);

        Livewire::test(CreateTask::class)
            ->fillForm([
                'summary' => 'Private venue task',
                'priority' => 5,
                'venue_id' => BookingForm::OTHER_VENUE,
                'name' => 'Temporary store cupboard',
                'street' => '1 High Street',
                'city' => 'Leicester',
                'postcode' => 'LE1 1AA',
                'country' => 'GB',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $task = Task::query()->where('summary', 'Private venue task')->first();
        $this->assertNotNull($task);

        $privateVenue = Venue::onlyPrivate()
            ->where('task_id', $task->getKey())
            ->whereKey($task->venue_id)
            ->first();

        $this->assertNotNull($privateVenue);
        $this->assertSame('Temporary store cupboard', $privateVenue->name);
        $this->assertFalse($team->venues()->whereKey($privateVenue->getKey())->exists());
    }

    public function test_managers_can_create_and_clear_tasks_without_a_venue(): void
    {
        $team = Team::factory()->withTasks()->create();
        $manager = User::factory()->teamManager($team)->create();

        $this->actingAs($manager);
        Filament::setTenant($team);

        Livewire::test(CreateTask::class)
            ->fillForm([
                'summary' => 'No venue task',
                'priority' => 5,
                'venue_id' => null,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $task = Task::query()->where('summary', 'No venue task')->first();
        $this->assertNotNull($task);
        $this->assertNull($task->venue_id);

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->assertSuccessful()
            ->assertDontSee(__('Venue'));

        $ics = app(TaskIcsFactory::class)->make([$task->fresh(['venue', 'bookings', 'attendance'])], 'Tasks');
        $this->assertStringContainsString('BEGIN:VTODO', $ics);
        $this->assertStringNotContainsString('LOCATION', $ics);

        $venue = Venue::factory()->create();
        $team->addVenue($venue);
        $task->update(['venue_id' => $venue->getKey()]);

        Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
            ->fillForm([
                'venue_id' => null,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($task->fresh()->venue_id);
    }

    public function test_clearing_venue_deletes_a_private_task_venue(): void
    {
        $team = Team::factory()->withTasks()->create();
        $manager = User::factory()->teamManager($team)->create();

        $this->actingAs($manager);
        Filament::setTenant($team);

        Livewire::test(CreateTask::class)
            ->fillForm([
                'summary' => 'Clear private venue',
                'priority' => 5,
                'venue_id' => BookingForm::OTHER_VENUE,
                'name' => 'Temporary store cupboard',
                'street' => '1 High Street',
                'city' => 'Leicester',
                'postcode' => 'LE1 1AA',
                'country' => 'GB',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $task = Task::query()->where('summary', 'Clear private venue')->first();
        $this->assertNotNull($task);
        $privateVenueId = $task->venue_id;
        $this->assertNotNull($privateVenueId);

        Livewire::test(EditTask::class, ['record' => $task->getRouteKey()])
            ->fillForm([
                'venue_id' => null,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($task->fresh()->venue_id);
        $this->assertNull(Venue::withPrivate()->find($privateVenueId));
    }

    public function test_complete_and_cancel_actions_drive_task_status(): void
    {
        $team = Team::factory()->withTasks()->create();
        $manager = User::factory()->teamManager($team)->create();
        $task = Task::factory()->forTeam($team)->create();

        $this->actingAs($manager);
        Filament::setTenant($team);

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->assertActionVisible('startTask')
            ->callAction('startTask');

        $this->assertSame(TaskStatus::InProcess, $task->fresh()->status);
        $this->assertNotNull($task->fresh()->started_at);

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->assertActionHidden('startTask')
            ->callAction('completeTask');

        $this->assertSame(TaskStatus::Completed, $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->callAction('reopenTask');

        $this->assertSame(TaskStatus::NeedsAction, $task->fresh()->status);
        $this->assertNull($task->fresh()->completed_at);

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->callAction('cancelTask');

        $this->assertTrue($task->fresh()->isCancelled());
    }

    public function test_delete_is_only_available_for_a_cancelled_task(): void
    {
        $team = Team::factory()->withTasks()->create();
        $manager = User::factory()->teamManager($team)->create();
        $open = Task::factory()->forTeam($team)->create();
        $cancelled = Task::factory()->forTeam($team)->cancelled()->create();

        $this->actingAs($manager);
        Filament::setTenant($team);

        $this->assertFalse($manager->can('delete', $open));
        $this->assertTrue($manager->can('delete', $cancelled));

        Livewire::test(EditTask::class, ['record' => $open->getRouteKey()])
            ->assertActionHidden('delete');

        Livewire::test(ViewTask::class, ['record' => $cancelled->getRouteKey()])
            ->assertActionVisible('delete');
    }

    public function test_task_view_shows_recent_activity_timeline(): void
    {
        $team = Team::factory()->withTasks()->create();
        $manager = User::factory()->teamManager($team)->create(['name' => "Manager O'Brien"]);

        $this->actingAs($manager);
        Filament::setTenant($team);

        $task = Task::factory()->forTeam($team)->create(['summary' => 'Timeline task']);
        $memberUrl = MemberResource::getUrl('view', ['record' => $manager], panel: 'teams', tenant: $team);

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->assertSee('Recent activity')
            ->assertSeeHtml('created the task')
            ->assertSeeHtml('href="'.$memberUrl.'"')
            ->assertSeeHtml("Manager O'Brien")
            ->assertDontSeeHtml('O&amp;#039;Brien');

        $task->markCompleted();

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->assertSee('Recent activity')
            ->assertSeeHtml('status');
    }

    public function test_complete_control_toggles_between_open_completed_and_cancelled(): void
    {
        $team = Team::factory()->withTasks()->create();
        $member = User::factory()->teamMember($team)->create();
        $task = Task::factory()->forTeam($team)->inProcess()->create();

        $this->actingAs($member);
        Filament::setTenant($team);

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->call('toggleTaskComplete', $task->getKey())
            ->assertSuccessful();

        $this->assertSame(TaskStatus::Completed, $task->fresh()->status);

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->call('toggleTaskComplete', $task->getKey())
            ->assertSuccessful();

        $this->assertSame(TaskStatus::NeedsAction, $task->fresh()->status);

        $task->markCancelled();

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->call('toggleTaskComplete', $task->getKey())
            ->assertSuccessful();

        $this->assertSame(TaskStatus::NeedsAction, $task->fresh()->status);
    }

    public function test_booking_tasks_panel_is_hidden_when_feature_off(): void
    {
        $team = Team::factory()->create();
        $manager = User::factory()->teamManager($team)->create();
        $booking = Booking::factory()->forTeam($team)->create();

        $this->assertFalse(BookingTasks::shouldShowPanel($booking, $manager));
    }

    public function test_task_related_bookings_default_to_future_and_can_show_past(): void
    {
        $team = Team::factory()->withTasks()->create();
        $manager = User::factory()->teamManager($team)->create();
        $task = Task::factory()->forTeam($team)->create();
        $future = Booking::factory()->forTeam($team)->create([
            'start_at' => now()->addDays(3),
            'end_at' => now()->addDays(3)->addHours(2),
        ]);
        $past = Booking::factory()->forTeam($team)->create([
            'start_at' => now()->subDays(5),
            'end_at' => now()->subDays(5)->addHours(2),
        ]);

        $task->attachBooking($future);
        $task->attachBooking($past);

        $this->actingAs($manager);

        Livewire::test(TaskRelatedBookings::class, ['record' => $task])
            ->assertSee($future->relatedLabel())
            ->assertDontSee($past->relatedLabel())
            ->call('toggleShowPast')
            ->assertSee($past->relatedLabel());
    }

    public function test_task_notes_render_as_markdown_on_the_view(): void
    {
        $team = Team::factory()->withTasks()->create();
        $manager = User::factory()->teamManager($team)->create();
        $task = Task::factory()->forTeam($team)->create([
            'description' => "Check the **bolts**.\n\n- Bring a wrench",
        ]);

        $this->actingAs($manager);
        Filament::setTenant($team);

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->assertSuccessful()
            ->assertSeeHtml('<strong>bolts</strong>')
            ->assertSeeHtml('<li>Bring a wrench</li>');
    }

    public function test_task_list_shows_a_plain_notes_summary_under_the_title(): void
    {
        $team = Team::factory()->withTasks()->create();
        $manager = User::factory()->teamManager($team)->create();
        $task = Task::factory()->forTeam($team)->create([
            'summary' => 'Pad check',
            'description' => "Check the **bolts**.\n\nBring a wrench",
        ]);

        $this->actingAs($manager);
        Filament::setTenant($team);

        Livewire::test(ListTasks::class)
            ->assertSuccessful()
            ->assertSee('Pad check')
            ->assertSee('Check the bolts. Bring a wrench')
            ->assertDontSee('**bolts**');
    }

    public function test_tasks_and_everything_feeds_include_vtodo_and_related_to(): void
    {
        $team = Team::factory()->withTasks()->create();
        $member = User::factory()->teamMember($team)->create([
            'feed_token' => User::generateToken(),
        ]);
        $booking = Booking::factory()->forTeam($team)->confirmed()->create();
        $task = Task::factory()->forTeam($team)->create([
            'summary' => 'Feed task',
            'priority' => 1,
            'description' => 'Replace the **signs**.',
            'due_at' => null,
        ]);
        $task->attachBooking($booking);

        $tasksFeed = str_replace("\r\n ", '', $this->get(route('task.ics', $member))
            ->assertOk()
            ->getContent());

        $this->assertStringContainsString('BEGIN:VTODO', $tasksFeed);
        $this->assertStringContainsString('SUMMARY:Feed task', $tasksFeed);
        $this->assertStringContainsString('DESCRIPTION:Replace the signs.', $tasksFeed);
        $this->assertStringNotContainsString('DESCRIPTION:Replace the **signs**.', $tasksFeed);
        $this->assertStringContainsString('X-ALT-DESC;FMTTYPE=text/html:', $tasksFeed);
        $this->assertStringContainsString('<strong>signs</strong>', $tasksFeed);
        $this->assertStringContainsString('RELATED-TO', $tasksFeed);
        $this->assertStringContainsString($booking->uid, $tasksFeed);

        $everything = str_replace("\r\n ", '', $this->get(route('calendar.everything.ics', $member))
            ->assertOk()
            ->getContent());

        $this->assertStringContainsString('BEGIN:VEVENT', $everything);
        $this->assertStringContainsString('BEGIN:VTODO', $everything);
    }

    public function test_view_booking_embeds_tasks_panel_when_feature_enabled(): void
    {
        $team = Team::factory()->withTasks()->create();
        $manager = User::factory()->teamManager($team)->create();
        $booking = Booking::factory()->forTeam($team)->create();
        Task::factory()->forTeam($team)->create()->attachBooking($booking);

        $this->actingAs($manager);
        Filament::setTenant($team);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSuccessful()
            ->assertSeeLivewire(BookingTasks::class);
    }

    public function test_team_features_toggle_persists_tasks_flag(): void
    {
        $team = Team::factory()->create(['features' => []]);
        $this->assertFalse($team->featureEnabled(TeamFeature::Tasks));

        $team->enableFeature(TeamFeature::Tasks);
        $team->save();

        $this->assertTrue($team->fresh()->featureEnabled(TeamFeature::Tasks));
    }
}
