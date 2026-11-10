<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TeamFeature;
use App\Filament\Teams\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Teams\Resources\Tasks\Pages\CreateTask;
use App\Filament\Teams\Resources\Tasks\Pages\ListTasks;
use App\Filament\Teams\Resources\Tasks\TaskResource;
use App\Livewire\BookingTasks;
use App\Livewire\TaskRelatedBookings;
use App\Models\Booking;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
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

        Livewire::test(CreateTask::class)
            ->fillForm([
                'summary' => 'Check bolts',
                'priority' => 1,
                'status' => TaskStatus::NeedsAction->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $task = Task::query()->where('summary', 'Check bolts')->first();
        $this->assertNotNull($task);
        $this->assertSame($team->getKey(), $task->team_id);

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
            ])
            ->assertNotified();

        $created = Task::query()->where('summary', 'Brand new task')->first();
        $this->assertNotNull($created);
        $this->assertTrue($booking->fresh()->tasks()->whereKey($created)->exists());

        Livewire::test(BookingTasks::class, ['record' => $booking->fresh()])
            ->call('remove', $existing->getKey())
            ->assertSuccessful();

        $this->assertFalse($booking->fresh()->tasks()->whereKey($existing)->exists());
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
            'description' => null,
            'location' => null,
            'due_at' => null,
        ]);
        $task->attachBooking($booking);

        $tasksFeed = str_replace("\r\n ", '', $this->get(route('task.ics', $member))
            ->assertOk()
            ->getContent());

        $this->assertStringContainsString('BEGIN:VTODO', $tasksFeed);
        $this->assertStringContainsString('SUMMARY:Feed task', $tasksFeed);
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
