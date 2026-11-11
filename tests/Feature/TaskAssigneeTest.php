<?php

namespace Tests\Feature;

use App\Actions\AssignTaskAction;
use App\Actions\RespondToTaskAssigneeAction;
use App\Enums\BookingAttendeeStatus;
use App\Enums\CalendarMethod;
use App\Enums\InboundMailOutcome;
use App\Enums\MailChannel;
use App\Enums\MailGroup;
use App\Enums\TaskAttendeeStatus;
use App\Enums\TaskStatus;
use App\Filament\Teams\Resources\Tasks\Pages\ViewTask;
use App\Jobs\SendTaskNotification;
use App\Livewire\TaskAssigneePanel;
use App\Mail\TaskChangedMail;
use App\Mail\TaskInviteMail;
use App\Models\Booking;
use App\Models\InboundMail;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Support\InboundMailAddress;
use App\Support\MailPreference;
use App\Support\TaskIcsFactory;
use App\Support\TaskNotificationWindow;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class TaskAssigneeTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_assign_and_invite_mail_includes_attendee_partstat(): void
    {
        Mail::fake();
        Queue::fake();

        [$team, $manager, $member, $task] = $this->taskContext();

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        app(AssignTaskAction::class)($task, $member, $manager);

        $task->refresh();
        $this->assertTrue($task->isAssignedTo($member));
        $this->assertSame(TaskAttendeeStatus::NeedsAction, $task->attendance?->status);

        foreach (Queue::pushed(SendTaskNotification::class) as $job) {
            $job->handle();
        }

        Mail::assertSent(TaskInviteMail::class, function (TaskInviteMail $mail) use ($member, $task): bool {
            if (! $mail->hasTo($member->email)) {
                return false;
            }

            $html = $mail->render();
            $ics = app(TaskIcsFactory::class)->make(
                [$task->fresh(['attendance', 'bookings', 'venue'])],
                $task->summary,
                method: CalendarMethod::Request,
                forAssignee: $member,
                rsvp: true,
            );

            return str_contains($html, 'You\'ve been invited to complete a team task.')
                && str_contains($html, 'Can you take this task?')
                && str_contains($html, 'Accept')
                && str_contains($html, 'Decline')
                && str_contains($html, 'Not started')
                && ! str_contains($html, 'Maybe')
                && str_contains($ics, 'BEGIN:VTODO')
                && str_contains($ics, 'ATTENDEE')
                && str_contains($ics, 'PARTSTAT=NEEDS-ACTION')
                && ! str_contains($ics, 'RELATED-TO');
        });
    }

    public function test_accepted_assignment_mail_uses_assigned_copy_and_view_response(): void
    {
        Mail::fake();
        Queue::fake();

        [$team, $manager, $member, $task] = $this->taskContext();

        app(AssignTaskAction::class)($task, $member, $manager, invite: false);
        $task->refresh();

        // Force an invite-style send against an already-accepted row (e.g. refresh).
        TaskNotificationWindow::push($task, TaskNotificationWindow::INVITE, assigneeUserId: (string) $member->getKey());

        foreach (Queue::pushed(SendTaskNotification::class) as $job) {
            $job->handle();
        }

        Mail::assertSent(TaskInviteMail::class, function (TaskInviteMail $mail) use ($member): bool {
            if (! $mail->hasTo($member->email)) {
                return false;
            }

            $html = $mail->render();

            return str_contains($html, 'You have been assigned a team task.')
                && str_contains($html, 'View response')
                && ! str_contains($html, 'Can you take this task?');
        });
    }

    public function test_task_update_mail_highlights_changed_fields(): void
    {
        Mail::fake();
        Queue::fake();

        [$team, $manager, $member, $task] = $this->taskContext();

        app(AssignTaskAction::class)($task, $member, $manager, invite: false);
        Queue::fake();

        $task->summary = 'Replace crash pads';
        $task->save();
        $task->notifyAssigneeUpdated(['summary']);

        foreach (Queue::pushed(SendTaskNotification::class) as $job) {
            $job->handle();
        }

        Mail::assertSent(TaskChangedMail::class, function (TaskChangedMail $mail) use ($member): bool {
            if (! $mail->hasTo($member->email)) {
                return false;
            }

            $html = $mail->render();

            return str_contains($html, 'A task assigned to you has changed.')
                && str_contains($html, 'Changes')
                && str_contains($html, 'Summary')
                && str_contains($html, 'Replace crash pads')
                && str_contains($html, '(changed)');
        });
    }

    public function test_adding_without_invite_marks_accepted_and_sends_no_mail(): void
    {
        Mail::fake();
        Queue::fake();

        [$team, $manager, $member, $task] = $this->taskContext();

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        app(AssignTaskAction::class)($task, $member, $manager, invite: false);

        $task->refresh();
        $this->assertTrue($task->isAssignedTo($member));
        $this->assertSame(TaskAttendeeStatus::Accepted, $task->attendance?->status);
        Queue::assertNotPushed(SendTaskNotification::class);
        Mail::assertNothingSent();
    }

    public function test_only_assignee_or_manager_can_complete_when_assigned(): void
    {
        [$team, $manager, $assignee, $task] = $this->taskContext();
        $other = User::factory()->teamMember($team)->create();

        app(AssignTaskAction::class)($task, $assignee, $manager);

        $this->assertTrue($assignee->can('complete', $task->fresh()));
        $this->assertTrue($manager->can('complete', $task->fresh()));
        $this->assertFalse($other->can('complete', $task->fresh()));
    }

    public function test_unassigned_tasks_remain_collaboratively_completable(): void
    {
        [$team, , $member, $task] = $this->taskContext();

        $this->assertTrue($member->can('complete', $task));
    }

    public function test_inbound_completed_reply_completes_the_task(): void
    {
        Mail::fake();

        [$team, $manager, $member, $task] = $this->taskContext();
        app(AssignTaskAction::class)($task, $member, $manager);
        $task->refresh();

        $this->postTaskCalendar($task, $member, 'REPLY', 'COMPLETED', (int) $task->sequence)
            ->assertOk();

        $task->refresh();
        $this->assertSame(TaskAttendeeStatus::Completed, $task->attendance?->status);
        $this->assertSame(TaskStatus::Completed, $task->status);
        $this->assertTrue($task->assigneeHasCalendarChannel());
        // Completing bumps SEQUENCE, so the inbound copy is treated as stale and resent.
        $this->assertContains(
            InboundMail::query()->first()?->outcome,
            [InboundMailOutcome::Applied, InboundMailOutcome::Resent],
        );
    }

    public function test_inbound_accepted_reply_only_updates_partstat(): void
    {
        [$team, $manager, $member, $task] = $this->taskContext();
        app(AssignTaskAction::class)($task, $member, $manager);
        $task->refresh();

        $this->postTaskCalendar($task, $member, 'REPLY', 'ACCEPTED', (int) $task->sequence)
            ->assertOk();

        $task->refresh();
        $this->assertSame(TaskAttendeeStatus::Accepted, $task->attendance?->status);
        $this->assertSame(TaskStatus::NeedsAction, $task->status);
    }

    public function test_rota_includes_assigned_tasks_for_accepted_partstat_only(): void
    {
        [$team, $manager, $member, $task] = $this->taskContext();
        $member->forceFill(['feed_token' => User::generateToken()])->save();

        app(AssignTaskAction::class)($task, $member, $manager);
        app(RespondToTaskAssigneeAction::class)(
            $task->fresh(),
            $member,
            TaskAttendeeStatus::Accepted,
            actor: $manager,
        );

        $needsAction = Task::factory()->forTeam($team)->create(['summary' => 'Pending invite']);
        app(AssignTaskAction::class)($needsAction, $member, $manager);

        $booking = Booking::factory()->forTeam($team)->create();
        $booking->attendees()->attach($member->getKey(), [
            'status' => BookingAttendeeStatus::Accepted->value,
        ]);

        $response = $this->actingAs($member)
            ->get(route('booking.rota.ics', $member));

        $response->assertOk();
        $body = $response->getContent();

        $this->assertStringContainsString('BEGIN:VEVENT', $body);
        $this->assertStringContainsString('BEGIN:VTODO', $body);
        $this->assertStringContainsString($task->summary, $body);
        $this->assertStringNotContainsString('Pending invite', $body);
    }

    public function test_task_mail_preference_blocks_invite_without_calendar_channel(): void
    {
        Mail::fake();
        Queue::fake();

        [$team, $manager, $member, $task] = $this->taskContext();
        MailPreference::save($member, MailGroup::Tasks, [
            MailChannel::TasksMail->value => '0',
        ]);

        app(AssignTaskAction::class)($task, $member, $manager);

        foreach (Queue::pushed(SendTaskNotification::class) as $job) {
            $job->handle();
        }

        Mail::assertNotSent(TaskInviteMail::class);
    }

    public function test_task_timeline_uses_natural_assignee_response_wording(): void
    {
        [$team, $manager, $member, $task] = $this->taskContext();

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        app(AssignTaskAction::class)($task, $member, $manager);
        app(RespondToTaskAssigneeAction::class)(
            $task->fresh(),
            $member,
            TaskAttendeeStatus::Accepted,
            actor: $manager,
        );

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->assertSee('invited')
            ->assertSee('recorded')
            ->assertSee('Accepted')
            ->assertDontSee('responded')
            ->assertDontSee('set '.$member->name.' to');

        app(RespondToTaskAssigneeAction::class)(
            $task->fresh(),
            $member,
            TaskAttendeeStatus::Declined,
            actor: $member,
        );

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->assertSee('declined the assignment')
            ->assertDontSee('responded declined');
    }

    public function test_assignee_panel_is_hidden_for_members_when_unassigned(): void
    {
        [$team, $manager, $member, $task] = $this->taskContext();

        $this->assertFalse(TaskAssigneePanel::shouldShowPanel($task, $member));
        $this->assertTrue(TaskAssigneePanel::shouldShowPanel($task, $manager));

        app(AssignTaskAction::class)($task, $member, $manager, invite: false);

        $this->assertTrue(TaskAssigneePanel::shouldShowPanel($task->fresh(), $member));
        $this->assertTrue(TaskAssigneePanel::shouldShowPanel($task->fresh(), $manager));
    }

    public function test_manager_can_assign_reassign_update_response_and_clear_from_panel(): void
    {
        [$team, $manager, $member, $task] = $this->taskContext();
        $replacement = User::factory()->teamMember($team)->create();

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(TaskAssigneePanel::class, ['record' => $task])
            ->assertActionHasLabel('assign', 'Assign')
            ->callAction('assign', data: [
                'assignee_id' => $member->getKey(),
                'invite' => 0,
            ])
            ->assertNotified();

        $task->refresh();
        $this->assertTrue($task->isAssignedTo($member));
        $this->assertSame(TaskAttendeeStatus::Accepted, $task->attendance?->status);

        Livewire::test(TaskAssigneePanel::class, ['record' => $task])
            ->assertActionHasLabel('assign', 'Reassign')
            ->callAction('overrideStatus', data: [
                'status' => TaskAttendeeStatus::Tentative->value,
            ])
            ->assertNotified();

        $this->assertSame(TaskAttendeeStatus::Tentative, $task->fresh()->attendance?->status);

        Livewire::test(TaskAssigneePanel::class, ['record' => $task->fresh()])
            ->callAction('assign', data: [
                'assignee_id' => $replacement->getKey(),
                'invite' => 0,
            ])
            ->assertNotified();

        $this->assertTrue($task->fresh()->isAssignedTo($replacement));

        Livewire::test(TaskAssigneePanel::class, ['record' => $task->fresh()])
            ->callAction('clear')
            ->assertNotified();

        $this->assertNull($task->fresh()->attendance);
    }

    /**
     * @return array{0: Team, 1: User, 2: User, 3: Task}
     */
    private function taskContext(): array
    {
        $team = Team::factory()->withTasks()->create();
        $manager = User::factory()->teamManager($team)->create();
        $member = User::factory()->teamMember($team)->create([
            'email' => 'assignee@example.com',
        ]);
        $task = Task::factory()->forTeam($team)->create([
            'summary' => 'Replace pads',
        ]);

        return [$team, $manager, $member, $task];
    }

    private function postTaskCalendar(Task $task, User $member, string $method, string $partstat, int $sequence)
    {
        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'METHOD:'.$method,
            'BEGIN:VTODO',
            'UID:'.$task->uid,
            'SEQUENCE:'.$sequence,
            'DTSTAMP:'.now()->format('Ymd\THis\Z'),
            'ATTENDEE;PARTSTAT='.$partstat.':mailto:'.$member->email,
            'END:VTODO',
            'END:VCALENDAR',
            '',
        ]);

        $to = InboundMailAddress::forTask($task);

        return $this->postMailWebhook([
            'subject' => 'Task reply',
            'date' => now()->toIso8601String(),
            'from' => [
                'text' => $member->email,
                'value' => [['address' => $member->email, 'name' => $member->name]],
            ],
            'to' => [
                'text' => $to,
                'value' => [['address' => $to, 'name' => null]],
            ],
            'attachments' => [[
                'contentType' => 'text/calendar',
                'content' => $ics,
            ]],
            'html' => '<p>reply</p>',
        ]);
    }
}
