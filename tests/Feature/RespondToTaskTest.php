<?php

namespace Tests\Feature;

use App\Actions\AssignTaskAction;
use App\Enums\TaskAttendeeStatus;
use App\Livewire\RespondToTask;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RespondToTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignee_can_accept_via_token_link(): void
    {
        [$team, $manager, $member, $task] = $this->assignedTask();

        Livewire::withQueryParams(['invite' => $task->attendance->token])
            ->test(RespondToTask::class, [
                'task' => $task,
                'assignee' => $member,
            ])
            ->assertSee('Respond to task')
            ->assertSee($task->summary)
            ->assertSee('Accept')
            ->assertSee('Decline')
            ->call('respond', TaskAttendeeStatus::Accepted->value)
            ->assertHasNoErrors()
            ->assertSee('You accepted this task.')
            ->assertDontSee('Accept');

        $this->assertSame(TaskAttendeeStatus::Accepted, $task->fresh()->attendance?->status);
    }

    public function test_assignee_can_decline_via_token_link(): void
    {
        [$team, $manager, $member, $task] = $this->assignedTask();

        Livewire::withQueryParams(['invite' => $task->attendance->token])
            ->test(RespondToTask::class, [
                'task' => $task,
                'assignee' => $member,
            ])
            ->call('respond', TaskAttendeeStatus::Declined->value)
            ->assertHasNoErrors()
            ->assertSee('You declined this task.');

        $this->assertSame(TaskAttendeeStatus::Declined, $task->fresh()->attendance?->status);
    }

    public function test_invalid_token_is_forbidden(): void
    {
        [$team, $manager, $member, $task] = $this->assignedTask();

        Livewire::withQueryParams(['invite' => 'not-the-token'])
            ->test(RespondToTask::class, [
                'task' => $task,
                'assignee' => $member,
            ])
            ->assertForbidden();
    }

    public function test_stale_sequence_shows_warning_before_confirm(): void
    {
        [$team, $manager, $member, $task] = $this->assignedTask();
        $token = $task->attendance->token;

        Livewire::withQueryParams([
            'invite' => $token,
            'choice' => TaskAttendeeStatus::Accepted->value,
            'sequence' => max(0, (int) $task->sequence - 1),
        ])
            ->test(RespondToTask::class, [
                'task' => $task,
                'assignee' => $member,
            ])
            ->assertSee('This task has changed since the email you opened');
    }

    /**
     * @return array{0: Team, 1: User, 2: User, 3: Task}
     */
    private function assignedTask(): array
    {
        $team = Team::factory()->withTasks()->create();
        $manager = User::factory()->teamManager($team)->create();
        $member = User::factory()->teamMember($team)->create();
        $task = Task::factory()->forTeam($team)->create(['summary' => 'Replace pads']);

        app(AssignTaskAction::class)($task, $member, $manager);

        return [$team, $manager, $member, $task->fresh(['attendance', 'venue', 'team'])];
    }
}
