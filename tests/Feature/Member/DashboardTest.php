<?php

namespace Tests\Feature\Member;

use App\Enums\BookingAttendeeStatus;
use App\Enums\MembershipRole;
use App\Enums\TaskAttendeeStatus;
use App\Enums\TaskPriority;
use App\Enums\TeamFeature;
use App\Filament\Member\Pages\Dashboard;
use App\Filament\Member\Resources\Tasks\TaskResource;
use App\Filament\Member\Widgets\AllCaughtUpWidget;
use App\Filament\Member\Widgets\AssignedTasksWidget;
use App\Filament\Member\Widgets\LatestNewsWidget;
use App\Filament\Member\Widgets\MyTeamsWidget;
use App\Filament\Member\Widgets\NeedsResponseWidget;
use App\Filament\Member\Widgets\NextLedBookingWidget;
use App\Filament\Member\Widgets\ThisWeekWidget;
use App\Filament\Member\Widgets\UnreadInboxWidget;
use App\Filament\Teams\Resources\NewsPosts\Pages\ViewNewsPost;
use App\Models\Booking;
use App\Models\NewsPost;
use App\Models\Task;
use App\Models\TaskAttendance;
use App\Models\Team;
use App\Models\User;
use App\Notifications\LeadNeededNotification;
use App\Notifications\NewsPublishedNotification;
use App\Support\MemberDashboard;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-03 12:00:00');
        Filament::setCurrentPanel('member');

        $this->member = User::factory()->teamMember()->create();
        $this->actingAs($this->member);
    }

    private function booking(string $start, ?BookingAttendeeStatus $status, array $attributes = []): Booking
    {
        $booking = Booking::factory()
            ->forTeam($this->member->teams()->firstOrFail())
            ->confirmed()
            ->create([
                'start_at' => $start,
                'end_at' => Carbon::parse($start)->addHours(2),
                'group_name' => null,
                ...$attributes,
            ]);

        if ($status !== null) {
            $booking->attendees()->attach($this->member->id, ['status' => $status->value]);
        }

        return $booking;
    }

    public function test_the_dashboard_page_shows_the_sections_in_the_fixed_order(): void
    {
        $this->assertSame([
            UnreadInboxWidget::class,
            ThisWeekWidget::class,
            NeedsResponseWidget::class,
            AssignedTasksWidget::class,
            NextLedBookingWidget::class,
            AllCaughtUpWidget::class,
            MyTeamsWidget::class,
            LatestNewsWidget::class,
        ], Livewire::test(Dashboard::class)->instance()->getWidgets());

        $this->get(Dashboard::getUrl(panel: 'member'))->assertOk();
    }

    public function test_it_shows_all_caught_up_and_no_other_section_when_nothing_needs_attention(): void
    {
        Livewire::test(AllCaughtUpWidget::class)
            ->assertSee("You're all caught up")
            ->assertSee('Open a team below to get started.')
            ->assertDontSee('Browse team bookings');
        Livewire::test(ThisWeekWidget::class)->assertDontSee('This week');
        Livewire::test(UnreadInboxWidget::class)->assertDontSee('Mark all read');
        Livewire::test(NeedsResponseWidget::class)->assertDontSee('Needs your response');
        Livewire::test(AssignedTasksWidget::class)->assertDontSee('Your tasks');
        Livewire::test(NextLedBookingWidget::class)->assertDontSee('Next booking you lead');
        Livewire::test(LatestNewsWidget::class)->assertDontSee('Latest news');
    }

    public function test_all_caught_up_omits_the_teams_prompt_when_the_member_has_no_teams(): void
    {
        $this->member->teams()->firstOrFail()->recordLeft($this->member);

        Livewire::test(AllCaughtUpWidget::class)
            ->assertSee("You're all caught up")
            ->assertDontSee('Open a team below to get started.')
            ->assertDontSee('Browse team bookings');
    }

    public function test_my_teams_lists_each_current_team_with_role_and_hides_when_none_remain(): void
    {
        $team = $this->member->teams()->firstOrFail();

        Livewire::test(MyTeamsWidget::class)
            ->assertSee('My Teams')
            ->assertSee($team->name)
            ->assertSee(MembershipRole::Member->getLabel());

        $second = Team::factory()->create(['name' => 'Zulu Climbers']);
        $second->grant($this->member, MembershipRole::Member);

        Livewire::test(MyTeamsWidget::class)
            ->assertSee($team->name)
            ->assertSee('Zulu Climbers');

        $team->recordLeft($this->member);
        $second->recordLeft($this->member);

        Livewire::test(MyTeamsWidget::class)->assertDontSee('My Teams');
    }

    public function test_this_week_lists_commitments_but_not_declined_or_cancelled_or_later_bookings(): void
    {
        $accepted = $this->booking('2026-11-05 18:00:00', BookingAttendeeStatus::Accepted);
        $awaiting = $this->booking('2026-11-04 18:00:00', BookingAttendeeStatus::NeedsAction);
        $declined = $this->booking('2026-11-04 10:00:00', BookingAttendeeStatus::Declined);
        $declinedLead = $this->booking('2026-11-04 11:00:00', BookingAttendeeStatus::Declined, ['lead_instructor_id' => $this->member->id]);
        $cancelled = $this->booking('2026-11-04 12:00:00', BookingAttendeeStatus::Accepted);
        $cancelled->update(['status' => 'cancelled']);
        $later = $this->booking('2026-11-20 12:00:00', BookingAttendeeStatus::Accepted);

        $widget = Livewire::test(ThisWeekWidget::class)
            ->assertSee('This week')
            ->assertSee('Can you help with this?');
        $ids = $widget->viewData('rows')->pluck('id')->all();

        $this->assertSame([$awaiting->id, $accepted->id], $ids);
        $this->assertNotContains($declined->id, $ids);
        $this->assertNotContains($declinedLead->id, $ids);
        $this->assertNotContains($cancelled->id, $ids);
        $this->assertNotContains($later->id, $ids);
        Livewire::test(AllCaughtUpWidget::class)->assertDontSee("You're all caught up");
    }

    public function test_the_window_uses_the_bookings_own_timezone(): void
    {
        $this->travelTo(Carbon::parse('2026-11-03 23:30:00', 'UTC'));

        $tokyo = $this->booking('2026-11-10 14:00:00', BookingAttendeeStatus::Accepted, ['timezone' => 'Asia/Tokyo']);
        $london = $this->booking('2026-11-10 14:00:00', BookingAttendeeStatus::Accepted, ['timezone' => 'Europe/London']);

        $ids = Livewire::test(ThisWeekWidget::class)->viewData('rows')->pluck('id')->all();

        // Tokyo is already on 4 Nov, so its seven days run to the end of 10 Nov and include this booking.
        $this->assertContains($tokyo->id, $ids);
        $this->assertNotContains($london->id, $ids);
    }

    public function test_led_tentative_bookings_appear_and_are_marked_tentative(): void
    {
        $this->booking('2026-11-05 18:00:00', BookingAttendeeStatus::Accepted, [
            'lead_instructor_id' => $this->member->id,
            'status' => 'tentative',
        ]);

        Livewire::test(ThisWeekWidget::class)
            ->assertSee('Lead instructor')
            ->assertSee('Tentative');
    }

    public function test_needs_response_separates_awaiting_from_maybe_and_limits_maybe_to_three_months(): void
    {
        $awaitingFar = $this->booking('2027-03-01 18:00:00', BookingAttendeeStatus::NeedsAction);
        $maybeSoon = $this->booking('2026-12-01 18:00:00', BookingAttendeeStatus::Tentative);
        $maybeFar = $this->booking('2027-03-01 18:00:00', BookingAttendeeStatus::Tentative);
        $inWindow = $this->booking('2026-11-05 18:00:00', BookingAttendeeStatus::NeedsAction);

        $widget = Livewire::test(NeedsResponseWidget::class)->assertSee('Needs your response');

        $this->assertSame([$awaitingFar->id], $widget->viewData('awaiting')->pluck('id')->all());
        $this->assertSame([$maybeSoon->id], $widget->viewData('maybe')->pluck('id')->all());
        $this->assertNotContains($maybeFar->id, $widget->viewData('maybe')->pluck('id')->all());
        $this->assertContains($inWindow->id, Livewire::test(ThisWeekWidget::class)->viewData('rows')->pluck('id')->all());
    }

    public function test_awaiting_response_shows_five_rows_and_the_total(): void
    {
        foreach (range(1, 7) as $day) {
            $this->booking('2026-12-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT).' 18:00:00', BookingAttendeeStatus::NeedsAction);
        }

        $widget = Livewire::test(NeedsResponseWidget::class)->assertSee('Awaiting response');

        $this->assertCount(5, $widget->viewData('awaiting'));
        $this->assertSame(7, $widget->viewData('awaitingTotal'));
    }

    public function test_next_led_day_lists_every_confirmed_booking_that_day_and_hides_when_inside_the_window(): void
    {
        $morning = $this->booking('2026-12-10 09:00:00', BookingAttendeeStatus::Accepted, ['lead_instructor_id' => $this->member->id]);
        $evening = $this->booking('2026-12-10 18:00:00', BookingAttendeeStatus::Accepted, ['lead_instructor_id' => $this->member->id]);
        $otherDay = $this->booking('2026-12-11 18:00:00', BookingAttendeeStatus::Accepted, ['lead_instructor_id' => $this->member->id]);

        $widget = Livewire::test(NextLedBookingWidget::class)->assertSee('Next booking you lead');

        $this->assertEquals([$morning->id, $evening->id], $widget->viewData('rows')->pluck('id')->all());
        $this->assertNotContains($otherDay->id, $widget->viewData('rows')->pluck('id')->all());

        $this->booking('2026-11-05 18:00:00', BookingAttendeeStatus::Accepted, ['lead_instructor_id' => $this->member->id]);

        Livewire::test(NextLedBookingWidget::class)->assertDontSee('Next booking you lead');
    }

    public function test_a_led_booking_that_has_not_been_accepted_shows_the_lead_warning(): void
    {
        $this->booking('2026-12-10 09:00:00', null, ['lead_instructor_id' => $this->member->id]);

        Livewire::test(NextLedBookingWidget::class)
            ->assertSee('Next booking you lead')
            ->assertSee('You are the lead instructor.');
    }

    public function test_a_booking_appears_in_one_section_only(): void
    {
        $booking = $this->booking('2026-12-10 09:00:00', BookingAttendeeStatus::NeedsAction, ['lead_instructor_id' => $this->member->id]);

        $this->assertSame([$booking->id], Livewire::test(NeedsResponseWidget::class)->viewData('awaiting')->pluck('id')->all());
        $this->assertTrue(Livewire::test(NextLedBookingWidget::class)->viewData('rows')->isEmpty());
    }

    public function test_responding_from_the_dashboard_updates_the_section_and_notifies_the_others(): void
    {
        $booking = $this->booking('2026-11-05 18:00:00', BookingAttendeeStatus::NeedsAction);

        Livewire::test(ThisWeekWidget::class)
            ->assertSee(__('Respond'))
            ->callAction('respondNo', arguments: ['booking' => (string) $booking->getKey()])
            ->assertHasNoErrors()
            ->assertDispatched('booking-attendance-updated')
            ->assertDontSee('This week');

        $this->assertSame(
            BookingAttendeeStatus::Declined,
            $booking->fresh()->attendees()->find($this->member->id)->attendance->status,
        );
        Livewire::test(AllCaughtUpWidget::class)->assertSee("You're all caught up");
    }

    public function test_unread_inbox_preview_refills_and_marks_rows_read(): void
    {
        $booking = $this->booking('2026-12-10 09:00:00', null);
        foreach (range(1, 7) as $i) {
            $this->travel($i)->minutes();
            $this->member->notify(new LeadNeededNotification($booking, null));
        }
        $this->travelTo(Carbon::parse('2026-11-03 12:30:00'));

        $component = Livewire::test(UnreadInboxWidget::class)->assertSee('Inbox')->assertSee('Mark shown read');

        $this->assertCount(5, $component->viewData('rows'));
        $this->assertSame(7, $component->viewData('total'));

        $component->call('markShownRead', $component->viewData('rows')->pluck('id')->all())
            ->assertDispatched('dashboard-updated');

        $this->assertCount(2, $component->viewData('rows'));
        $this->assertSame(2, $component->viewData('total'));

        $component->assertDontSee('Mark shown read')->call('markRead', $component->viewData('rows')->first()->id);
        $this->assertSame(1, $this->member->unreadNotifications()->count());

        $component->call('markAllRead')->assertDontSee('Mark all read');
        $this->assertSame(0, $this->member->unreadNotifications()->count());
    }

    public function test_mark_read_cannot_touch_another_members_notifications(): void
    {
        $other = User::factory()->teamMember()->create();
        $booking = $this->booking('2026-12-10 09:00:00', null);
        $other->notify(new LeadNeededNotification($booking, null));

        Livewire::test(UnreadInboxWidget::class)->call('markRead', $other->notifications()->first()->id);

        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_latest_news_shows_the_newest_recent_post_with_a_new_marker_and_reading_it_clears_the_notice(): void
    {
        $team = $this->member->teams()->firstOrFail();
        NewsPost::factory()->forTeam($team)->published()->create([
            'title' => 'Stale announcement',
            'published_at' => now()->subDays(31),
        ]);

        Livewire::test(LatestNewsWidget::class)->assertDontSee('Latest news');

        $post = NewsPost::factory()->forTeam($team)->published()->create([
            'title' => 'Camp is on',
            'published_at' => now()->subDays(2),
        ]);
        NewsPost::factory()->forTeam(Team::factory()->create())->published()->create(['title' => 'Other team news', 'published_at' => now()->subHour()]);

        $widget = Livewire::test(LatestNewsWidget::class)
            ->assertSee('Latest news')
            ->assertSee('Camp is on')
            ->assertDontSee('Other team news');
        $this->assertFalse($widget->viewData('isNew'));

        $this->member->notify(new NewsPublishedNotification($post));

        $this->assertTrue(Livewire::test(LatestNewsWidget::class)->viewData('isNew'));

        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);
        Livewire::test(ViewNewsPost::class, ['record' => $post->slug]);

        $this->assertSame(0, $this->member->unreadNotifications()->count());
        $this->assertSame(1, $this->member->notifications()->whereNotNull('digest_notified_at')->count());
    }

    public function test_news_does_not_hide_the_all_caught_up_message(): void
    {
        NewsPost::factory()->forTeam($this->member->teams()->firstOrFail())->published()->create(['published_at' => now()->subDay()]);

        Livewire::test(AllCaughtUpWidget::class)->assertSee("You're all caught up");
        Livewire::test(LatestNewsWidget::class)->assertSee('Latest news');
    }

    public function test_a_failing_section_is_reported_and_never_shown_as_all_caught_up(): void
    {
        $this->app->bind(MemberDashboard::class, fn ($app, array $parameters) => new class($parameters['user']) extends MemberDashboard
        {
            public function thisWeek(): array
            {
                throw new RuntimeException('boom');
            }
        });

        Livewire::test(ThisWeekWidget::class)->assertSee('Something went wrong loading part of your dashboard');
        Livewire::test(AllCaughtUpWidget::class)->assertDontSee("You're all caught up");
    }

    public function test_marking_notices_read_refreshes_the_sidebar_and_topbar_badges(): void
    {
        Livewire::test(UnreadInboxWidget::class)
            ->call('markAllRead')
            ->assertDispatched('refresh-sidebar')
            ->assertDispatched('refresh-topbar');
    }

    public function test_bookings_that_have_ended_are_dimmed_in_this_week(): void
    {
        $this->booking('2026-11-03 08:00:00', BookingAttendeeStatus::Accepted);

        Livewire::test(ThisWeekWidget::class)->assertSeeHtml('opacity-60');

        Livewire::test(ThisWeekWidget::class)->call('$refresh');
        $this->travelTo(Carbon::parse('2026-11-03 07:00:00'));

        Livewire::test(ThisWeekWidget::class)->assertDontSeeHtml('opacity-60');
    }

    public function test_your_tasks_lists_overdue_before_assigned_and_includes_needs_action_invites(): void
    {
        $team = $this->enableTasks();
        $overdue = $this->assignedTask($team, TaskAttendeeStatus::Accepted, [
            'summary' => 'Overdue pad check',
            'due_at' => now()->subDay(),
            'priority' => TaskPriority::Low,
        ]);
        $invite = $this->assignedTask($team, TaskAttendeeStatus::NeedsAction, [
            'summary' => 'Invite to restock',
            'due_at' => now()->addDays(3),
            'priority' => TaskPriority::High,
        ]);
        $laterLow = $this->assignedTask($team, TaskAttendeeStatus::Accepted, [
            'summary' => 'Later low priority',
            'due_at' => now()->addDays(5),
            'priority' => TaskPriority::Low,
        ]);
        $this->assignedTask($team, TaskAttendeeStatus::Declined, [
            'summary' => 'Declined task',
            'due_at' => now()->addDay(),
        ]);

        $widget = Livewire::test(AssignedTasksWidget::class)
            ->assertSee('Your tasks')
            ->assertSee('Overdue')
            ->assertSee('Assigned')
            ->assertSee('Overdue pad check')
            ->assertSee('overdue')
            ->assertSee('Invite to restock')
            ->assertSee('Awaiting response')
            ->assertDontSee('Declined task');

        $this->assertSame([$overdue->id], $widget->viewData('overdue')->pluck('id')->all());
        $this->assertSame([$invite->id, $laterLow->id], $widget->viewData('assigned')->pluck('id')->all());
        $this->assertSame(TaskResource::getUrl('index'), $widget->viewData('tasksUrl'));
        Livewire::test(AllCaughtUpWidget::class)->assertDontSee("You're all caught up");
    }

    public function test_your_tasks_alone_prevents_all_caught_up(): void
    {
        $this->assignedTask($this->enableTasks(), TaskAttendeeStatus::Accepted, [
            'summary' => 'Only open task',
            'due_at' => now()->addWeek(),
        ]);

        Livewire::test(AssignedTasksWidget::class)->assertSee('Your tasks');
        Livewire::test(AllCaughtUpWidget::class)->assertDontSee("You're all caught up");
    }

    public function test_completing_a_task_from_the_dashboard_refreshes_sections(): void
    {
        $task = $this->assignedTask($this->enableTasks(), TaskAttendeeStatus::Accepted, [
            'summary' => 'Toggle me',
            'due_at' => now()->addDay(),
        ]);

        Livewire::test(AssignedTasksWidget::class)
            ->assertSee('Toggle me')
            ->call('toggleTaskComplete', (string) $task->getKey())
            ->assertDispatched('dashboard-updated')
            ->assertDontSee('Your tasks');

        $this->assertTrue($task->fresh()->isComplete());
        Livewire::test(AllCaughtUpWidget::class)->assertSee("You're all caught up");
    }

    private function enableTasks(?Team $team = null): Team
    {
        $team ??= $this->member->teams()->firstOrFail();
        $team->forceFill(['features' => [TeamFeature::Tasks->value => true]])->save();

        return $team->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assignedTask(Team $team, TaskAttendeeStatus $status, array $attributes = []): Task
    {
        $task = Task::factory()->forTeam($team)->create($attributes);

        TaskAttendance::query()->create([
            'task_id' => $task->getKey(),
            'user_id' => $this->member->getKey(),
            'status' => $status,
        ]);

        return $task->fresh(['attendance', 'team']);
    }
}
