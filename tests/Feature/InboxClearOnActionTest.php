<?php

namespace Tests\Feature;

use App\Actions\ApproveMerchandiseOrderAction;
use App\Actions\DeleteUserAction;
use App\Actions\PassKeyAction;
use App\Enums\MembershipRole;
use App\Filament\Teams\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Teams\Resources\MerchandiseOrders\Pages\ViewMerchandiseOrder;
use App\Filament\Teams\Resources\NewsPosts\Pages\ViewNewsPost;
use App\Filament\Teams\Resources\Tasks\Pages\ViewTask;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\Comment;
use App\Models\Key;
use App\Models\Lock;
use App\Models\MerchandiseOrder;
use App\Models\NewsPost;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\AttendanceRecordedNotification;
use App\Notifications\BookingHeldNotification;
use App\Notifications\CommentNotification;
use App\Notifications\EmailBouncedNotification;
use App\Notifications\KeyHolderLeavingNotification;
use App\Notifications\LastManagerLeavingNotification;
use App\Notifications\LeadNeededNotification;
use App\Notifications\MerchandiseOrderApprovedNotification;
use App\Notifications\MerchandiseOrderRejectedNotification;
use App\Notifications\MerchandiseOrderSubmittedNotification;
use App\Notifications\NewsPublishedNotification;
use App\Notifications\TaskHeldNotification;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InboxClearOnActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewing_a_booking_marks_attention_notices_read_but_not_lead_needed(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $booking = Booking::factory()->create(['team_id' => $team->getKey()]);
        $comment = Comment::factory()->forBooking($booking)->create();

        $manager->notify(new AttendanceRecordedNotification($booking, $manager));
        $manager->notify(new BookingHeldNotification($booking, 'Held update'));
        $manager->notify(new CommentNotification($comment));
        $manager->notify(new LeadNeededNotification($booking, null));

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()]);

        $this->assertNotNull($manager->notifications()->where('type', AttendanceRecordedNotification::class)->first()?->read_at);
        $this->assertNotNull($manager->notifications()->where('type', BookingHeldNotification::class)->first()?->read_at);
        $this->assertNotNull($manager->notifications()->where('type', CommentNotification::class)->first()?->read_at);
        $this->assertNull($manager->notifications()->where('type', LeadNeededNotification::class)->first()?->read_at);
    }

    public function test_viewing_a_task_marks_held_and_comment_notices_read(): void
    {
        $team = Team::factory()->withTasks()->create();
        $manager = User::factory()->teamManager($team)->create();
        $task = Task::factory()->forTeam($team)->create();
        $comment = Comment::factory()->forTask($task)->create();

        $manager->notify(new TaskHeldNotification($task, 'Held task'));
        $manager->notify(new CommentNotification($comment));

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(ViewTask::class, ['record' => $task->getRouteKey()])
            ->assertSuccessful();

        $this->assertNotNull($manager->notifications()->where('type', TaskHeldNotification::class)->first()?->read_at);
        $this->assertNotNull($manager->notifications()->where('type', CommentNotification::class)->first()?->read_at);
    }

    public function test_viewing_news_still_marks_the_published_notice_read(): void
    {
        $member = User::factory()->teamMember()->create();
        $team = $member->teams()->firstOrFail();
        $post = NewsPost::factory()->forTeam($team)->published()->create();

        $member->notify(new NewsPublishedNotification($post));

        $this->actingAs($member);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(ViewNewsPost::class, ['record' => $post->slug]);

        $this->assertNotNull($member->notifications()->where('type', NewsPublishedNotification::class)->first()?->read_at);
    }

    public function test_viewing_a_merchandise_order_marks_outcome_notices_read_but_not_submitted(): void
    {
        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();
        $member = User::factory()->teamMember($team)->create();
        $order = MerchandiseOrder::factory()->for($team)->for($member)->withLine()->create();
        $approved = MerchandiseOrder::factory()->for($team)->for($member)->approved()->withLine()->create();
        $rejected = MerchandiseOrder::factory()->for($team)->for($member)->rejected()->withLine()->create();

        $manager->notify(new MerchandiseOrderSubmittedNotification($order));
        $member->notify(new MerchandiseOrderApprovedNotification($approved));
        $member->notify(new MerchandiseOrderRejectedNotification($rejected));

        $this->actingAs($manager);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(ViewMerchandiseOrder::class, ['record' => $order->getKey()])
            ->assertSuccessful();

        $this->assertNull($manager->notifications()->where('type', MerchandiseOrderSubmittedNotification::class)->first()?->read_at);

        $this->actingAs($member);

        Livewire::test(ViewMerchandiseOrder::class, ['record' => $approved->getKey()])
            ->assertSuccessful();
        Livewire::test(ViewMerchandiseOrder::class, ['record' => $rejected->getKey()])
            ->assertSuccessful();

        $this->assertNotNull($member->notifications()->where('type', MerchandiseOrderApprovedNotification::class)->first()?->read_at);
        $this->assertNotNull($member->notifications()->where('type', MerchandiseOrderRejectedNotification::class)->first()?->read_at);
    }

    public function test_approving_a_merchandise_order_marks_submitted_notices_read(): void
    {
        $team = Team::factory()->withShop()->create();
        $manager = User::factory()->teamManager($team)->create();
        $member = User::factory()->teamMember($team)->create();
        $order = MerchandiseOrder::factory()->for($team)->for($member)->withLine()->create();

        $manager->notify(new MerchandiseOrderSubmittedNotification($order));

        app(ApproveMerchandiseOrderAction::class)($order, $manager);

        $this->assertNotNull($manager->notifications()->where('type', MerchandiseOrderSubmittedNotification::class)->first()?->read_at);
    }

    public function test_updating_a_bounced_members_email_marks_bounce_inbox_rows_read(): void
    {
        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->teamMember($manager->managedTeams()->firstOrFail())->create([
            'email' => 'old@example.com',
            'email_bounced_at' => now(),
            'email_bounce_category' => 'recipient',
        ]);

        $manager->notify(new EmailBouncedNotification($member));

        $this->assertSame(1, $manager->unreadNotifications()->where('type', EmailBouncedNotification::class)->count());

        $member->forceFill(['email' => 'new@example.com'])->save();

        $this->assertNull($member->fresh()->email_bounced_at);
        $this->assertNotNull($manager->notifications()->where('type', EmailBouncedNotification::class)->first()?->read_at);
    }

    public function test_reassigning_keys_from_a_deleted_holder_marks_leaving_notices_read(): void
    {
        [$team, $lock] = $this->teamWithLock();
        $manager = User::factory()->teamManager($team)->create();
        $holder = User::factory()->teamMember($team)->create();
        $ring = Key::factory()->unlocks($lock)->heldBy($holder)->create();

        app(DeleteUserAction::class)->delete($holder, $holder, 'password');

        $this->assertSame(1, $manager->unreadNotifications()->where('type', KeyHolderLeavingNotification::class)->count());

        (new PassKeyAction)($manager, $ring->fresh(), null);

        $this->assertNull($ring->fresh()->holder_id);
        $this->assertNotNull($manager->notifications()->where('type', KeyHolderLeavingNotification::class)->first()?->read_at);
    }

    public function test_partial_key_reassignment_leaves_holder_leaving_notice_unread(): void
    {
        [$team, $lock] = $this->teamWithLock();
        $manager = User::factory()->teamManager($team)->create();
        $holder = User::factory()->teamMember($team)->create();
        $first = Key::factory()->unlocks($lock)->heldBy($holder)->create(['name' => 'First']);
        $second = Key::factory()->unlocks($lock)->heldBy($holder)->create(['name' => 'Second']);

        app(DeleteUserAction::class)->delete($holder, $holder, 'password');

        $this->assertSame(1, $manager->unreadNotifications()->where('type', KeyHolderLeavingNotification::class)->count());

        (new PassKeyAction)($manager, $first->fresh(), null);

        $this->assertNull($manager->notifications()->where('type', KeyHolderLeavingNotification::class)->first()?->read_at);

        (new PassKeyAction)($manager, $second->fresh(), null);

        $this->assertNotNull($manager->notifications()->where('type', KeyHolderLeavingNotification::class)->first()?->read_at);
    }

    /**
     * @return array{0: Team, 1: Lock}
     */
    private function teamWithLock(): array
    {
        $team = Team::factory()->create();
        $activity = Activity::factory()->create();
        $venue = Venue::factory()->create();
        $team->activities()->sync([$activity->getKey()]);
        $team->venues()->sync([$venue->getKey()]);

        $lock = Lock::factory()
            ->requiredFor($activity, $venue)
            ->create();

        return [$team, $lock];
    }

    public function test_appointing_a_manager_marks_last_manager_leaving_notices_read(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $replacement = User::factory()->teamMember($team)->create();

        app(DeleteUserAction::class)->delete($manager, $manager, 'password');

        $this->assertSame(1, $admin->unreadNotifications()->where('type', LastManagerLeavingNotification::class)->count());

        $team->grant($replacement, MembershipRole::Manager);

        $this->assertNotNull($admin->notifications()->where('type', LastManagerLeavingNotification::class)->first()?->read_at);
    }
}
