<?php

namespace Tests\Feature\Member;

use App\Enums\MembershipRole;
use App\Enums\TaskAttendeeStatus;
use App\Enums\TeamFeature;
use App\Filament\Member\Resources\Tasks\Pages\ListTasks;
use App\Filament\Member\Resources\Tasks\TaskResource;
use App\Filament\Teams\Resources\Tasks\TaskResource as TeamsTaskResource;
use App\Models\Task;
use App\Models\TaskAttendance;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('member');
    }

    public function test_the_member_task_list_shows_assigned_open_tasks_across_teams_with_team_filter(): void
    {
        $member = User::factory()->teamMember()->create();
        $team = $member->teams()->firstOrFail();
        $team->forceFill(['features' => [TeamFeature::Tasks->value => true]])->save();

        $second = Team::factory()->withTasks()->create(['name' => 'Second Team']);
        $second->grant($member, MembershipRole::Member);

        $mine = Task::factory()->forTeam($team)->create([
            'summary' => 'Mine on first',
            'due_at' => now()->addDays(2),
        ]);
        TaskAttendance::query()->create([
            'task_id' => $mine->getKey(),
            'user_id' => $member->getKey(),
            'status' => TaskAttendeeStatus::Accepted,
        ]);

        $invite = Task::factory()->forTeam($second)->create([
            'summary' => 'Invite on second',
            'due_at' => now()->addDay(),
        ]);
        TaskAttendance::query()->create([
            'task_id' => $invite->getKey(),
            'user_id' => $member->getKey(),
            'status' => TaskAttendeeStatus::NeedsAction,
        ]);

        $declined = Task::factory()->forTeam($team)->create(['summary' => 'Declined']);
        TaskAttendance::query()->create([
            'task_id' => $declined->getKey(),
            'user_id' => $member->getKey(),
            'status' => TaskAttendeeStatus::Declined,
        ]);

        $unassigned = Task::factory()->forTeam($team)->create(['summary' => 'Nobody']);

        $this->actingAs($member);

        Livewire::test(ListTasks::class)
            ->assertCanSeeTableRecords([$invite, $mine], inOrder: true)
            ->assertCanNotSeeTableRecords([$declined, $unassigned])
            ->assertTableColumnStateSet('team.name', $team->name, $mine)
            ->filterTable('team_id', $team->getKey())
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$invite]);

        $this->assertSame(
            TeamsTaskResource::getUrl('view', ['record' => $mine], panel: 'teams', tenant: $team),
            TaskResource::viewUrl($mine->fresh('team')),
        );
    }

    public function test_assignees_can_complete_a_task_from_the_member_list(): void
    {
        $member = User::factory()->teamMember()->create();
        $team = $member->teams()->firstOrFail();
        $team->forceFill(['features' => [TeamFeature::Tasks->value => true]])->save();

        $task = Task::factory()->forTeam($team)->create(['summary' => 'Finish me']);
        TaskAttendance::query()->create([
            'task_id' => $task->getKey(),
            'user_id' => $member->getKey(),
            'status' => TaskAttendeeStatus::Accepted,
        ]);

        $this->actingAs($member);

        Livewire::test(ListTasks::class)
            ->assertCanSeeTableRecords([$task])
            ->call('toggleTaskComplete', $task->getKey())
            ->assertSuccessful()
            ->assertCanNotSeeTableRecords([$task]);

        $this->assertTrue($task->fresh()->isComplete());
    }
}
