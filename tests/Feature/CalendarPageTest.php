<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Enums\PastBookingAttendeeStatus;
use App\Enums\TaskAttendeeStatus;
use App\Enums\TaskStatus;
use App\Enums\TeamFeature;
use App\Filament\Member\Pages\Calendar as MemberCalendar;
use App\Filament\Teams\Pages\Calendar as TeamsCalendar;
use App\Filament\Widgets\ScheduleCalendarWidget;
use App\Models\Booking;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Support\Calendar\ScheduleCalendarEvents;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Saade\FilamentFullCalendar\Data\FetchInfo;
use Tests\TestCase;

class CalendarPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Event titles may include a leading attendance icon as HTML.
     */
    private function calendarEventTitled(string $title): Closure
    {
        return fn (array $event): bool => str_contains(
            html_entity_decode(strip_tags($event['title'] ?? '')),
            $title,
        );
    }

    public function test_member_calendar_page_is_reachable_and_defaults_to_rota(): void
    {
        $member = User::factory()->teamMember()->create();

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        $this->get(MemberCalendar::getUrl(panel: 'member'))
            ->assertOk();

        Livewire::test(ScheduleCalendarWidget::class)
            ->assertSet('activeTab', 'rota');
    }

    public function test_teams_calendar_page_is_reachable_for_the_tenant(): void
    {
        $member = User::factory()->teamMember()->create();
        $team = $member->teams()->firstOrFail();

        Filament::setCurrentPanel('teams');
        $this->actingAs($member);
        Filament::setTenant($team);

        $this->get(TeamsCalendar::getUrl(panel: 'teams', tenant: $team))
            ->assertOk();
    }

    public function test_guests_cannot_open_the_calendar(): void
    {
        $this->get(MemberCalendar::getUrl(panel: 'member'))
            ->assertRedirect();
    }

    public function test_rota_tab_shows_accepted_bookings_and_hides_unrelated_confirmed_team_bookings(): void
    {
        $member = User::factory()->teamMember()->create();
        $team = $member->teams()->firstOrFail();

        $mine = Booking::factory()->forTeam($team)->confirmed()->create([
            'activity_label' => 'My accepted session',
            'activity_id' => null,
            'start_at' => '2026-12-10 10:00:00',
            'end_at' => '2026-12-10 12:00:00',
        ]);
        $mine->attendees()->attach($member->getKey(), [
            'status' => BookingAttendeeStatus::Accepted->value,
        ]);

        Booking::factory()->forTeam($team)->confirmed()->create([
            'activity_label' => 'Team only session',
            'activity_id' => null,
            'start_at' => '2026-12-11 10:00:00',
            'end_at' => '2026-12-11 12:00:00',
        ]);

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Livewire::test(ScheduleCalendarWidget::class)
            ->assertSet('activeTab', 'rota')
            ->assertCalendarHasEvent($this->calendarEventTitled('My accepted session'))
            ->assertCalendarDoesNotHaveEvent($this->calendarEventTitled('Team only session'))
            ->set('activeTab', 'all')
            ->assertCalendarHasEvent($this->calendarEventTitled('My accepted session'))
            ->assertCalendarHasEvent($this->calendarEventTitled('Team only session'));
    }

    public function test_teams_calendar_is_scoped_to_the_current_tenant(): void
    {
        $climbing = Team::factory()->create(['name' => 'Climbing']);
        $archery = Team::factory()->create(['name' => 'Archery']);
        $manager = User::factory()->teamManager($climbing)->create();
        $archery->grant($manager, MembershipRole::Manager);

        Booking::factory()->forTeam($climbing)->confirmed()->create([
            'activity_label' => 'Climbing session',
            'activity_id' => null,
            'start_at' => '2026-12-10 10:00:00',
            'end_at' => '2026-12-10 12:00:00',
        ]);
        Booking::factory()->forTeam($archery)->confirmed()->create([
            'activity_label' => 'Archery session',
            'activity_id' => null,
            'start_at' => '2026-12-10 14:00:00',
            'end_at' => '2026-12-10 16:00:00',
        ]);

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);
        Filament::setTenant($climbing);

        Livewire::test(ScheduleCalendarWidget::class)
            ->set('activeTab', 'all')
            ->assertCalendarHasEvent($this->calendarEventTitled('Climbing session'))
            ->assertCalendarDoesNotHaveEvent($this->calendarEventTitled('Archery session'));
    }

    public function test_rota_includes_assigned_tasks_as_all_day_events(): void
    {
        $member = User::factory()->teamMember()->create();
        $team = $member->teams()->firstOrFail();
        $team->forceFill(['features' => [TeamFeature::Tasks->value => true]])->save();

        $task = Task::factory()->forTeam($team)->create([
            'summary' => 'Pack the ropes',
            'due_at' => '2026-12-15 17:00:00',
        ]);
        $task->attendance()->create([
            'user_id' => $member->getKey(),
            'status' => TaskAttendeeStatus::Accepted,
        ]);

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        $events = Livewire::test(ScheduleCalendarWidget::class)
            ->getCalendarEvents('2026-12-01', '2027-01-01');

        $match = collect($events)->first($this->calendarEventTitled('Pack the ropes'));

        $this->assertNotNull($match);
        $this->assertTrue($match['allDay'] ?? false);
        $this->assertSame('task-'.$task->getKey(), $match['id']);
    }

    public function test_past_bookings_appear_when_the_visible_range_includes_them(): void
    {
        $member = User::factory()->teamMember()->create();
        $team = $member->teams()->firstOrFail();

        $past = Booking::factory()->forTeam($team)->confirmed()->create([
            'activity_label' => 'Past rota session',
            'activity_id' => null,
            'start_at' => '2026-01-10 10:00:00',
            'end_at' => '2026-01-10 12:00:00',
        ]);
        $past->attendees()->attach($member->getKey(), [
            'status' => BookingAttendeeStatus::Accepted->value,
        ]);

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Livewire::test(ScheduleCalendarWidget::class)
            ->assertCalendarHasEvent($this->calendarEventTitled('Past rota session'), '2026-01-01', '2026-02-01');
    }

    public function test_tentative_bookings_get_a_stripe_class(): void
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::Tentative,
        ]);

        $classes = ScheduleCalendarEvents::bookingClassNames($booking, BookingAttendeeStatus::Tentative);

        $this->assertContains('fc-event--tentative', $classes);
        $this->assertContains('fc-event--attendance-tentative', $classes);
        $this->assertContains('fc-event--booking', $classes);
    }

    public function test_incomplete_tasks_are_not_muted_on_the_calendar(): void
    {
        $open = Task::factory()->create(['status' => TaskStatus::NeedsAction]);
        $inProcess = Task::factory()->inProcess()->create();
        $completed = Task::factory()->completed()->create();

        $this->assertNotContains('fc-event--muted', ScheduleCalendarEvents::taskClassNames($open));
        $this->assertNotContains('fc-event--muted', ScheduleCalendarEvents::taskClassNames($inProcess));
        $this->assertContains('fc-event--muted', ScheduleCalendarEvents::taskClassNames($completed));
    }

    public function test_booking_tooltip_omits_team_when_it_matches_the_activity_title(): void
    {
        $team = Team::factory()->create(['name' => 'Climbing']);
        $booking = Booking::factory()->forTeam($team)->confirmed()->create([
            'activity_label' => 'Climbing',
            'activity_id' => null,
        ]);
        $booking->setRelation('venue', null);

        $rows = ScheduleCalendarEvents::bookingTooltipRows($booking);

        $this->assertSame(
            ['Climbing', 'Confirmed'],
            array_column($rows, 'text'),
        );

        $html = ScheduleCalendarEvents::bookingTooltipHtml($booking);

        $this->assertStringContainsString('fi-fc-event-tooltip', $html);
        $this->assertStringContainsString('Climbing', $html);
        $this->assertStringContainsString('Confirmed', $html);
        $this->assertStringContainsString('fi-fc-event-tooltip__icon', $html);
    }

    public function test_past_booking_attendee_status_maps_for_calendar_icons(): void
    {
        $this->assertSame(
            BookingAttendeeStatus::Accepted,
            ScheduleCalendarEvents::bookingAttendeeStatus(PastBookingAttendeeStatus::Accepted),
        );
        $this->assertSame(
            BookingAttendeeStatus::Tentative,
            ScheduleCalendarEvents::bookingAttendeeStatus(PastBookingAttendeeStatus::Tentative),
        );
    }

    public function test_booking_calendar_events_use_block_display_and_attendance_classes(): void
    {
        $member = User::factory()->teamMember()->create();
        $team = $member->teams()->firstOrFail();
        $booking = Booking::factory()->forTeam($team)->create([
            'status' => BookingStatus::Tentative,
            'activity_label' => 'Climbing',
            'activity_id' => null,
            'start_at' => '2026-11-22 09:00:00',
            'end_at' => '2026-11-22 11:00:00',
        ]);
        $booking->attendees()->attach($member->getKey(), [
            'status' => BookingAttendeeStatus::Accepted->value,
        ]);
        $booking->load('venue');

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        $event = collect(Livewire::test(ScheduleCalendarWidget::class)
            ->getCalendarEvents('2026-11-01', '2026-12-01'))
            ->first(fn (array $candidate): bool => str_contains(
                strip_tags((string) ($candidate['title'] ?? '')),
                'Climbing',
            ));

        $this->assertNotNull($event);
        $this->assertSame('block', $event['display'] ?? null);

        $classes = $event['className'] ?? $event['classNames'] ?? [];
        $classes = is_array($classes) ? $classes : preg_split('/\s+/', (string) $classes);

        $this->assertContains('fc-event--tentative', $classes);
        $this->assertContains('fc-event--attendance-accepted', $classes);
        $this->assertNotEmpty($event['color'] ?? null);
        $this->assertStringContainsString('✓', strip_tags((string) $event['title']));
        $this->assertStringContainsString('fi-fc-event-subtitle', (string) $event['title']);
        $this->assertStringContainsString((string) $booking->venue?->name, strip_tags((string) $event['title']));
    }

    public function test_accepted_tasks_do_not_use_a_completed_tick_icon(): void
    {
        $open = Task::factory()->create(['status' => TaskStatus::NeedsAction]);
        $completed = Task::factory()->completed()->create();

        $this->assertSame('☐', ScheduleCalendarEvents::taskLeadingIcon($open, TaskAttendeeStatus::Accepted));
        $this->assertSame('✓', ScheduleCalendarEvents::taskLeadingIcon($completed, TaskAttendeeStatus::Accepted));
        $this->assertSame('↻', ScheduleCalendarEvents::taskLeadingIcon(
            Task::factory()->inProcess()->create(),
            TaskAttendeeStatus::Accepted,
        ));
        $this->assertSame(
            '<span class="fi-fc-event-leading-icon" aria-hidden="true">☐</span> Re-lay Slabs',
            ScheduleCalendarEvents::titleWithLeadingIcon('Re-lay Slabs', '☐'),
        );
        $this->assertSame(
            '<span class="fi-fc-event-leading-icon" aria-hidden="true">✓</span> Climbing'
            .'<div class="fi-fc-event-subtitle">Fox Coverts</div>',
            ScheduleCalendarEvents::eventTitleHtml('Climbing', '✓', 'Fox Coverts'),
        );
        $this->assertSame(
            'Climbing',
            ScheduleCalendarEvents::eventTitleHtml('Climbing'),
        );
    }

    public function test_calendar_restores_view_and_date_from_the_query_string(): void
    {
        $member = User::factory()->teamMember()->create();

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        $component = Livewire::withQueryParams(['view' => 'timeGridWeek', 'date' => '2026-03-15'])
            ->test(ScheduleCalendarWidget::class)
            ->assertSet('calendarView', 'timeGridWeek')
            ->assertSet('calendarDate', '2026-03-15');

        $config = $component->instance()->config();

        $this->assertSame('timeGridWeek', $config['initialView']);
        $this->assertSame('2026-03-15', $config['initialDate']);
        $this->assertTrue($config['borderlessX']);
        $this->assertTrue($config['borderlessBottom']);
    }

    public function test_dates_set_updates_view_and_date_for_reload_and_back(): void
    {
        $member = User::factory()->teamMember()->create();

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Livewire::test(ScheduleCalendarWidget::class)
            ->call('handleDatesSet', [
                'view' => 'listMonth',
                'title' => 'March 2026',
                'start' => '2026-02-23T00:00:00+00:00',
                'end' => '2026-04-06T00:00:00+00:00',
                'currentStart' => '2026-03-01T00:00:00+00:00',
                'currentEnd' => '2026-04-01T00:00:00+00:00',
            ])
            ->assertSet('calendarView', 'listMonth')
            ->assertSet('calendarDate', '2026-03-01');
    }

    public function test_system_admin_without_membership_does_not_see_team_schedule_on_the_member_calendar(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $team = Team::factory()->create([
            'features' => [TeamFeature::Tasks->value => true],
        ]);
        Booking::factory()->forTeam($team)->confirmed()->create([
            'activity_label' => 'Admin should not see booking',
            'activity_id' => null,
            'start_at' => '2026-12-10 10:00:00',
            'end_at' => '2026-12-10 12:00:00',
        ]);
        Task::factory()->forTeam($team)->create([
            'summary' => 'Admin should not see task',
            'due_at' => '2026-12-15 17:00:00',
        ]);

        Filament::setCurrentPanel('member');
        $this->actingAs($admin);

        $this->assertFalse($admin->can('viewAny', Booking::class));

        $info = new FetchInfo(
            start: CarbonImmutable::parse('2026-12-01'),
            end: CarbonImmutable::parse('2027-01-01'),
            timezone: config('app.timezone'),
            raw: ['start' => '2026-12-01', 'end' => '2027-01-01', 'timezone' => config('app.timezone')],
        );

        $events = (new ScheduleCalendarEvents($admin, tab: 'all'))->events($info);

        $this->assertSame([], $events);
    }
}
