<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Filament\Teams\Pages\Reports;
use App\Models\Activity;
use App\Models\Audience;
use App\Models\Booking;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use App\Support\ManagerBookingReports;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManagerBookingReportsTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private ManagerBookingReports $reports;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create(['name' => 'Climbing']);
        $this->reports = app(ManagerBookingReports::class);
    }

    public function test_summary_counts_confirmed_cancelled_and_year_on_year(): void
    {
        $activity = Activity::factory()->create(['name' => 'Climbing']);
        $this->team->addActivity($activity);
        $venue = Venue::factory()->create(['name' => 'Hut']);
        $this->team->addVenue($venue);

        Booking::factory()->forTeam($this->team)->confirmed()->create([
            'activity_id' => $activity->getKey(),
            'venue_id' => $venue->getKey(),
            'start_at' => '2025-03-10 09:00:00',
            'end_at' => '2025-03-10 11:00:00',
        ]);
        Booking::factory()->forTeam($this->team)->confirmed()->create([
            'activity_id' => $activity->getKey(),
            'venue_id' => $venue->getKey(),
            'start_at' => '2025-06-10 09:00:00',
            'end_at' => '2025-06-10 12:00:00',
        ]);
        Booking::factory()->forTeam($this->team)->cancelled()->create([
            'activity_id' => $activity->getKey(),
            'venue_id' => $venue->getKey(),
            'start_at' => '2025-04-10 09:00:00',
            'end_at' => '2025-04-10 11:00:00',
        ]);
        Booking::factory()->forTeam($this->team)->confirmed()->create([
            'activity_id' => $activity->getKey(),
            'venue_id' => $venue->getKey(),
            'start_at' => '2024-05-10 09:00:00',
            'end_at' => '2024-05-10 11:00:00',
        ]);

        $summary = $this->reports->summary($this->team, 2025);

        $this->assertSame(2, $summary['confirmed']['current']);
        $this->assertSame(1, $summary['confirmed']['previous']);
        $this->assertSame(1, $summary['confirmed']['absolute']);
        $this->assertSame(1, $summary['cancelled']['current']);
        $this->assertSame(0, $summary['cancelled']['previous']);
        $this->assertEqualsWithDelta(1 / 3, $summary['cancellation_rate']['current'], 0.0001);
        $this->assertSame(5.0, $summary['scheduled_hours']['current']);
        $this->assertSame(2.0, $summary['scheduled_hours']['previous']);

        $trend = $this->reports->yearTrend($this->team, 2026);
        $this->assertSame([2024, 2025], array_column($trend, 'year'));
        $this->assertSame([], array_column($this->reports->yearTrend($this->team, 2023), 'year'));
    }

    public function test_past_tentative_hygiene_and_breakdowns_are_team_scoped(): void
    {
        $other = Team::factory()->create(['name' => 'Archery']);
        $activity = Activity::factory()->create(['name' => 'Top rope']);
        $audience = Audience::factory()->create(['name' => 'Youth']);
        $venue = Venue::factory()->create(['name' => 'Wall']);
        $this->team->addActivity($activity);
        $this->team->addVenue($venue);

        Booking::factory()->forTeam($this->team)->create([
            'activity_id' => $activity->getKey(),
            'audience_id' => $audience->getKey(),
            'venue_id' => $venue->getKey(),
            'status' => BookingStatus::Tentative,
            'start_at' => now()->subDays(10)->setTime(9, 0),
            'end_at' => now()->subDays(10)->setTime(11, 0),
        ]);
        Booking::factory()->forTeam($this->team)->confirmed()->create([
            'activity_id' => $activity->getKey(),
            'audience_id' => $audience->getKey(),
            'venue_id' => $venue->getKey(),
            'start_at' => now()->setTime(9, 0),
            'end_at' => now()->setTime(11, 0),
        ]);
        Booking::factory()->forTeam($other)->create([
            'status' => BookingStatus::Tentative,
            'start_at' => now()->subDays(5)->setTime(9, 0),
            'end_at' => now()->subDays(5)->setTime(11, 0),
        ]);

        $year = (int) now()->year;

        $this->assertSame(1, $this->reports->pastTentativeLiveCount($this->team));
        $this->assertSame(1, $this->reports->pastTentativeBookings($this->team, $year)->count());
        $this->assertSame(0, $this->reports->pastTentativeBookings($this->team, $year - 1)->count());

        $byActivity = $this->reports->breakdownByActivity($this->team, $year);
        $this->assertTrue($byActivity->contains(fn (array $row): bool => $row['label'] === 'Top rope' && $row['confirmed']['current'] === 1));

        $byAudience = $this->reports->breakdownByAudience($this->team, $year);
        $this->assertTrue($byAudience->contains(fn (array $row): bool => $row['label'] === 'Youth'));

        $byVenue = $this->reports->breakdownByVenue($this->team, $year);
        $this->assertTrue($byVenue->contains(fn (array $row): bool => $row['label'] === 'Wall'));
    }

    public function test_member_engagement_flags_never_responded_and_rates(): void
    {
        $silent = User::factory()->create(['name' => 'Silent Sam']);
        $helper = User::factory()->create(['name' => 'Helpful Helen']);
        $this->team->grant($silent, MembershipRole::Member);
        $this->team->grant($helper, MembershipRole::Member);

        $year = 2025;
        $first = Booking::factory()->forTeam($this->team)->confirmed()->create([
            'start_at' => '2025-02-01 09:00:00',
            'end_at' => '2025-02-01 11:00:00',
            'lead_instructor_id' => $helper->getKey(),
        ]);
        $second = Booking::factory()->forTeam($this->team)->confirmed()->create([
            'start_at' => '2025-03-01 09:00:00',
            'end_at' => '2025-03-01 11:00:00',
        ]);

        $first->attendees()->attach($silent->getKey(), ['status' => BookingAttendeeStatus::NeedsAction->value]);
        $second->attendees()->attach($silent->getKey(), ['status' => BookingAttendeeStatus::NeedsAction->value]);
        $first->attendees()->attach($helper->getKey(), ['status' => BookingAttendeeStatus::Accepted->value]);
        $second->attendees()->attach($helper->getKey(), ['status' => BookingAttendeeStatus::Declined->value]);

        $rows = $this->reports->memberEngagement($this->team, $year);
        $silentRow = $rows->first(fn (array $row): bool => $row['user']->is($silent));
        $helperRow = $rows->first(fn (array $row): bool => $row['user']->is($helper));

        $this->assertNotNull($silentRow);
        $this->assertTrue($silentRow['never_responded']);
        $this->assertSame(2, $silentRow['invites']);
        $this->assertSame(0.0, $silentRow['response_rate']);
        $this->assertSame(100.0, $silentRow['awaiting_share']);
        $this->assertSame(0.0, $silentRow['accepted_share']);

        $this->assertNotNull($helperRow);
        $this->assertFalse($helperRow['never_responded']);
        $this->assertSame(1.0, $helperRow['response_rate']);
        $this->assertSame(0.5, $helperRow['can_help_rate']);
        $this->assertSame(50.0, $helperRow['accepted_share']);
        $this->assertSame(50.0, $helperRow['declined_share']);
        $this->assertSame(0.0, $helperRow['tentative_share']);
        $this->assertSame(1, $helperRow['leads']);
        $this->assertTrue($rows->first()['user']->is($helper));
        $this->assertSame(2, $rows->first()['responded']);
        $this->assertTrue($rows->last()['never_responded']);
        $this->assertSame(0, $rows->last()['responded']);

        $quietMany = User::factory()->create(['name' => 'Quiet Many']);
        $quietFew = User::factory()->create(['name' => 'Quiet Few']);
        $this->team->grant($quietMany, MembershipRole::Member);
        $this->team->grant($quietFew, MembershipRole::Member);
        $first->attendees()->attach($quietMany->getKey(), ['status' => BookingAttendeeStatus::NeedsAction->value]);
        $second->attendees()->attach($quietMany->getKey(), ['status' => BookingAttendeeStatus::NeedsAction->value]);
        $first->attendees()->attach($quietFew->getKey(), ['status' => BookingAttendeeStatus::NeedsAction->value]);

        $rows = $this->reports->memberEngagement($this->team, $year);
        $silent = $rows->filter(fn (array $row): bool => $row['responded'] === 0)->values();

        $this->assertSame(2, $silent[0]['invites']);
        $this->assertTrue($silent[0]['user']->is($quietMany));
        $this->assertSame(1, $silent->last()['invites']);
    }

    public function test_reports_page_is_manager_only(): void
    {
        $manager = User::factory()->create();
        $member = User::factory()->create();
        $admin = User::factory()->systemAdmin()->create();
        $this->team->grant($manager, MembershipRole::Manager);
        $this->team->grant($member, MembershipRole::Member);

        Filament::setCurrentPanel('teams');

        $this->actingAs($member);
        $this->get(Reports::getUrl(panel: 'teams', tenant: $this->team))->assertForbidden();

        $this->actingAs($admin);
        $this->get(Reports::getUrl(panel: 'teams', tenant: $this->team))->assertForbidden();

        $this->actingAs($manager);
        Filament::setTenant($this->team);
        $this->get(Reports::getUrl(panel: 'teams', tenant: $this->team))
            ->assertOk()
            ->assertSee(__('Overview'))
            ->assertSee(__('Member engagement'));

        Livewire::test(Reports::class)
            ->assertSet('year', (int) now()->year)
            ->assertSee(__('By activity'));

        $year = (int) now()->year;
        $cancelledHistoryUrl = Livewire::test(Reports::class)
            ->set('year', $year)
            ->instance()
            ->historyBookingsUrl(BookingStatus::Cancelled);

        $this->assertStringContainsString('tab=history', $cancelledHistoryUrl);
        $this->assertStringContainsString('filters%5Bstatus%5D%5Bvalue%5D=cancelled', $cancelledHistoryUrl);
        $this->assertStringContainsString('filters%5Bschedule%5D%5Bfrom%5D='.$year.'-01-01', $cancelledHistoryUrl);
        $this->assertStringContainsString('filters%5Bschedule%5D%5Bto%5D='.$year.'-12-31', $cancelledHistoryUrl);
        $this->assertStringNotContainsString('activeTab=', $cancelledHistoryUrl);
        $this->assertStringNotContainsString('tableFilters', $cancelledHistoryUrl);
    }
}
