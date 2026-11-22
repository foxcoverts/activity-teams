<?php

namespace Tests\Feature;

use App\Actions\AssignTaskAction;
use App\Actions\PostCommentAction;
use App\Enums\MailChannel;
use App\Enums\MembershipRole;
use App\Enums\TaskAttendeeStatus;
use App\Enums\TaskStatus;
use App\Filament\Member\Resources\Inbox\InboxResource;
use App\Filament\Teams\Resources\Tasks\Pages\ViewTask as TeamsViewTask;
use App\Livewire\Comments;
use App\Mail\CommentMail;
use App\Models\Comment;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Notifications\CommentNotification;
use App\Support\TaskMailThread;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class TaskCommentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignee_and_manager_can_post_and_other_members_cannot_when_assigned(): void
    {
        [$team, $manager, $assignee, $task] = $this->party(assigned: true);
        $other = User::factory()->teamMember($team)->create();

        $this->actingAs($assignee);
        Livewire::test(Comments::class, ['record' => $task])
            ->set('body', 'Pads are in.')
            ->call('post')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('comments', [
            'commentable_type' => Task::class,
            'commentable_id' => $task->getKey(),
            'author_id' => $assignee->getKey(),
            'body' => 'Pads are in.',
        ]);

        $this->actingAs($manager);
        Livewire::test(Comments::class, ['record' => $task])
            ->set('body', 'Thanks.')
            ->call('post')
            ->assertHasNoErrors();

        $this->assertFalse($other->can('create', [Comment::class, $task]));
        $this->actingAs($other);
        Livewire::test(Comments::class, ['record' => $task])
            ->assertDontSee(__('Add a comment...'));
    }

    public function test_unassigned_team_members_can_post(): void
    {
        [$team, , $member, $task] = $this->party(assigned: false);

        $this->actingAs($member);
        Livewire::test(Comments::class, ['record' => $task])
            ->set('body', 'I can take this.')
            ->call('post')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('comments', [
            'commentable_id' => $task->getKey(),
            'body' => 'I can take this.',
        ]);
    }

    public function test_completed_and_cancelled_tasks_are_closed_for_new_comments(): void
    {
        [$team, $manager, $assignee, $task] = $this->party(assigned: true);

        $completed = Task::factory()->forTeam($team)->create([
            'status' => TaskStatus::Completed,
        ]);
        $cancelled = Task::factory()->forTeam($team)->create([
            'status' => TaskStatus::Cancelled,
        ]);

        Comment::factory()->forTask($completed)->create([
            'author_id' => $assignee->getKey(),
            'body' => 'History',
        ]);

        $this->assertFalse($manager->can('create', [Comment::class, $completed]));
        $this->assertFalse($assignee->can('create', [Comment::class, $cancelled]));

        $this->actingAs($manager);
        Livewire::test(Comments::class, ['record' => $completed])
            ->assertSee('History')
            ->assertDontSee(__('Add a comment...'));
    }

    public function test_the_composer_hides_and_returns_when_task_status_changes_without_remounting(): void
    {
        [, $manager, $assignee, $task] = $this->party(assigned: true);

        Comment::factory()->forTask($task)->create([
            'author_id' => $assignee->getKey(),
            'body' => 'History',
        ]);

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($task->team);

        $comments = Livewire::test(Comments::class, ['record' => $task])
            ->assertSee('History')
            ->assertSee(__('Add a comment...'));

        $task->markCompleted();

        $comments
            ->dispatch('task-status-changed', taskId: (string) $task->getKey())
            ->assertSee('History')
            ->assertDontSee(__('Add a comment...'));

        $task->reopen();

        $comments
            ->dispatch('task-status-changed', taskId: (string) $task->getKey())
            ->assertSee('History')
            ->assertSee(__('Add a comment...'));

        Livewire::test(TeamsViewTask::class, ['record' => $task->getRouteKey()])
            ->call('toggleTaskComplete', $task->getKey())
            ->assertDispatched('task-status-changed');
    }

    public function test_system_admin_without_membership_cannot_post(): void
    {
        [, , , $task] = $this->party(assigned: false);
        $admin = User::factory()->systemAdmin()->create();

        $this->assertFalse($admin->can('create', [Comment::class, $task]));
    }

    public function test_assignee_badge_is_shown_and_lead_is_not(): void
    {
        [, $manager, $assignee, $task] = $this->party(assigned: true);

        Comment::factory()->forTask($task)->create([
            'author_id' => $assignee->getKey(),
            'body' => 'Done the bolts.',
        ]);

        $this->actingAs($manager);
        Livewire::test(Comments::class, ['record' => $task])
            ->assertSee(__('Assignee'))
            ->assertDontSee(__('Lead'))
            ->assertSee('Done the bolts.');
    }

    public function test_posting_notifies_assignee_and_managers_skipping_author_and_declined_assignee(): void
    {
        Mail::fake();

        [$team, $manager, $assignee, $task] = $this->party(assigned: true);
        $otherManager = User::factory()->teamManager($team)->create();
        $author = User::factory()->teamMember($team)->create();
        $team->grant($author, MembershipRole::Manager);

        Filament::setCurrentPanel('member');
        $comment = (new PostCommentAction)($task, $author, 'Check the ropes.');

        $this->assertSame(0, $author->notifications()->count());

        foreach ([$manager, $otherManager, $assignee] as $recipient) {
            $row = $recipient->notifications()->get()->first(
                fn ($notification): bool => ($notification->data['rebuild']['comment_id'] ?? null) === $comment->getKey(),
            );
            $this->assertNotNull($row);
            $this->assertSame(CommentNotification::class, $row->type);
            $this->assertSame(MailChannel::TasksComments->value, $row->data['mail_channel']);
            $this->assertStringContainsString('comment='.$comment->getKey(), $row->data['url']);
            $this->assertNotNull($row->read_at);
        }

        Mail::fake();
        $task->attendance()->update(['status' => TaskAttendeeStatus::Declined->value]);
        $task->unsetRelation('attendance');

        $second = (new PostCommentAction)($task, $author, 'Still need this.');

        $this->assertNull(
            $assignee->fresh()->notifications()->get()->first(
                fn ($notification): bool => ($notification->data['rebuild']['comment_id'] ?? null) === $second->getKey(),
            ),
        );
        $this->assertNotNull(
            $manager->fresh()->notifications()->get()->first(
                fn ($notification): bool => ($notification->data['rebuild']['comment_id'] ?? null) === $second->getKey(),
            ),
        );
    }

    public function test_unassigned_task_comment_notifies_managers_only(): void
    {
        Mail::fake();

        [$team, $manager, $member, $task] = $this->party(assigned: false);

        Filament::setCurrentPanel('member');
        (new PostCommentAction)($task, $member, 'Anyone free?');

        $this->assertSame(0, $member->notifications()->count());
        $this->assertSame(1, $manager->notifications()->count());
        Mail::assertQueued(CommentMail::class, fn (CommentMail $mail): bool => $mail->hasTo($manager->email));
    }

    public function test_comment_mail_threads_with_the_task_and_respects_preferences(): void
    {
        Mail::fake();

        [, $manager, $assignee, $task] = $this->party(assigned: true);

        Filament::setCurrentPanel('member');
        $comment = (new PostCommentAction)($task, $assignee, 'Bring a torch.');

        Mail::assertQueued(CommentMail::class, function (CommentMail $mail) use ($manager): bool {
            return $mail->hasTo($manager->email);
        });

        $built = new CommentMail($comment, $manager);
        $reference = '<'.TaskMailThread::reference($task).'>';

        $built->assertHasSubject(TaskMailThread::subject($task, 'Re'));
        $this->assertSame($reference, $built->headers()->referencesString());
        $this->assertSame($reference, $built->headers()->text['In-Reply-To']);
        $this->assertArrayNotHasKey('List-Unsubscribe', $built->headers()->text);

        Mail::fake();
        $manager->forceFill([
            'mail_preferences' => [
                MailChannel::TasksComments->value => false,
            ],
        ])->save();

        $quiet = (new PostCommentAction)($task, $assignee, 'Quiet please.');

        Mail::assertNothingOutgoing();
        $row = $manager->notifications()->get()->first(
            fn ($notification): bool => ($notification->data['rebuild']['comment_id'] ?? null) === $quiet->getKey(),
        );
        $this->assertNotNull($row);
        $this->assertNull($row->read_at);

        $this->actingAs($manager);
        $this->assertSame(1, InboxResource::unreadCount());
    }

    public function test_highlight_query_and_view_page_show_comments(): void
    {
        [, $manager, $assignee, $task] = $this->party(assigned: true);
        $older = Comment::factory()->forTask($task)->create([
            'author_id' => $manager->getKey(),
            'body' => 'Older note',
            'created_at' => now()->subHour(),
        ]);
        $newer = Comment::factory()->forTask($task)->create([
            'author_id' => $assignee->getKey(),
            'body' => 'Newer note',
            'created_at' => now(),
        ]);

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($task->team);

        Livewire::test(Comments::class, [
            'record' => $task,
            'highlightId' => $newer->getKey(),
        ])
            ->assertSee('Newer note')
            ->assertDontSee('Older note')
            ->call('showOlderComments')
            ->assertSee('Older note');

        Livewire::test(TeamsViewTask::class, ['record' => $task->getRouteKey()])
            ->assertSee('Comments')
            ->assertSee('Newer note')
            ->assertSee('Recent activity');
    }

    /**
     * @return array{0: Team, 1: User, 2: User, 3: Task}
     */
    private function party(bool $assigned): array
    {
        $team = Team::factory()->withTasks()->create();
        $manager = User::factory()->teamManager($team)->create();
        $member = User::factory()->teamMember($team)->create();
        $task = Task::factory()->forTeam($team)->create([
            'summary' => 'Replace pads',
        ]);

        if ($assigned) {
            app(AssignTaskAction::class)($task, $member, $manager, invite: false);
            $task->refresh();
        }

        return [$team, $manager, $member, $task];
    }
}
