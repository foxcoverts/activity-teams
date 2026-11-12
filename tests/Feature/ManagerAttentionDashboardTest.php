<?php

namespace Tests\Feature;

use App\Actions\ClearLeadInstructorAction;
use App\Actions\UpdateBookingStatusAction;
use App\Enums\AttentionSeverity;
use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Enums\TentativeTriage;
use App\Filament\Teams\Pages\ManagerDashboard;
use App\Filament\Teams\Widgets\Manager\EmailBouncesWidget;
use App\Filament\Teams\Widgets\Manager\NeedsLeadWidget;
use App\Filament\Teams\Widgets\Manager\NeedsStaffingWidget;
use App\Filament\Teams\Widgets\Manager\ReadyToConfirmWidget;
use App\Models\Booking;
use App\Models\Team;
use App\Models\User;
use App\Notifications\LeadNeededNotification;
use App\Support\ManagerAttention;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class ManagerAttentionDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $manager;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->teamManager()->create();
        $this->team = $this->manager->managedTeams()->first();
        $this->member = User::factory()->create();
        $this->team->grant($this->member, MembershipRole::Member);

        Filament::setCurrentPanel('teams');
        $this->actingAs($this->manager);
        Filament::setTenant($this->team);
    }

    public function test_non_managers_cannot_access_the_manager_dashboard(): void
    {
        $this->actingAs($this->member);
        Filament::setTenant($this->team);

        $this->assertFalse(ManagerDashboard::canAccess());
        $this->assertFalse(ManagerDashboard::shouldRegisterNavigation());

        $this->get(ManagerDashboard::getUrl(tenant: $this->team))->assertForbidden();
    }

    public function test_managers_can_open_the_manager_dashboard(): void
    {
        $this->assertTrue(ManagerDashboard::canAccess());
        $this->get(ManagerDashboard::getUrl(tenant: $this->team))->assertOk();
    }

    public function test_system_admins_can_open_the_manager_dashboard_without_membership(): void
    {
        $admin = User::factory()->systemAdmin()->create();

        $this->actingAs($admin);
        Filament::setTenant($this->team);

        $this->assertTrue(ManagerDashboard::canAccess());
        $this->get(ManagerDashboard::getUrl(tenant: $this->team))->assertOk();
    }

    public function test_clearing_a_confirmed_lead_stamps_lead_needed_and_resolves_inbox_when_fixed(): void
    {
        $this->actingAs($this->manager);

        $lead = User::factory()->create();
        $this->team->grant($lead, MembershipRole::Member);
        $booking = Booking::factory()->forTeam($this->team)->confirmed()->create([
            'lead_instructor_id' => $lead->getKey(),
            'start_at' => now()->addDays(5),
            'end_at' => now()->addDays(5)->addHours(2),
            'activity_label' => 'Needs lead session',
        ]);
        $booking->attendees()->attach($lead->getKey(), ['status' => BookingAttendeeStatus::Accepted->value]);

        $this->manager->notify(new LeadNeededNotification($booking, $lead));
        $otherManager = User::factory()->create();
        $this->team->grant($otherManager, MembershipRole::Manager);
        $otherManager->notify(new LeadNeededNotification($booking, $lead));

        (new ClearLeadInstructorAction)($booking->fresh(), $this->manager);

        $booking->refresh();
        $this->assertSame(BookingStatus::Tentative, $booking->status);
        $this->assertNotNull($booking->lead_needed_at);
        $this->assertSame(TentativeTriage::NeedsLead, ManagerAttention::for($this->team)->classify($booking));

        $this->assertNull($this->manager->notifications()->first()->read_at);
        $this->assertNull($otherManager->notifications()->first()->read_at);

        app(UpdateBookingStatusAction::class)->changeLead($booking->fresh(), $this->manager, $lead->getKey());

        $booking->refresh();
        $this->assertNull($booking->lead_needed_at);
        $this->assertNotNull($this->manager->notifications()->first()->fresh()->read_at);
        $this->assertNotNull($otherManager->notifications()->first()->fresh()->read_at);
    }

    public function test_confirming_clears_lead_needed(): void
    {
        $this->actingAs($this->manager);

        $lead = User::factory()->create();
        $booking = Booking::factory()->forTeam($this->team)->create([
            'status' => BookingStatus::Tentative,
            'lead_needed_at' => now(),
            'lead_instructor_id' => $lead->getKey(),
            'requires_lead_instructor' => true,
            'start_at' => now()->addDays(3),
            'end_at' => now()->addDays(3)->addHours(2),
        ]);
        $booking->attendees()->attach($lead->getKey(), ['status' => BookingAttendeeStatus::Accepted->value]);

        app(UpdateBookingStatusAction::class)->confirm(
            $booking->fresh(),
            $this->manager,
            $lead->getKey(),
            ['requires_lead_instructor' => true],
        );

        $this->assertNull($booking->fresh()->lead_needed_at);
        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
    }

    public function test_triage_buckets_ready_unstaffed_at_risk_and_lead_not_required(): void
    {
        $ready = Booking::factory()->forTeam($this->team)->create([
            'activity_label' => 'Ready session',
            'requires_lead_instructor' => false,
            'start_at' => now()->addDays(20),
            'end_at' => now()->addDays(20)->addHours(2),
        ]);
        $ready->attendees()->attach($this->member->getKey(), ['status' => BookingAttendeeStatus::Accepted->value]);

        $unstaffed = Booking::factory()->forTeam($this->team)->create([
            'activity_label' => 'Unstaffed far out',
            'requires_lead_instructor' => false,
            'start_at' => now()->addDays(30),
            'end_at' => now()->addDays(30)->addHours(2),
        ]);

        $atRisk = Booking::factory()->forTeam($this->team)->create([
            'activity_label' => 'At risk soon',
            'requires_lead_instructor' => false,
            'start_at' => now()->addDays(7),
            'end_at' => now()->addDays(7)->addHours(2),
        ]);

        $needsLead = Booking::factory()->forTeam($this->team)->create([
            'activity_label' => 'Lost its lead',
            'requires_lead_instructor' => true,
            'lead_needed_at' => now(),
            'start_at' => now()->addDays(4),
            'end_at' => now()->addDays(4)->addHours(2),
        ]);
        $needsLead->attendees()->attach($this->member->getKey(), ['status' => BookingAttendeeStatus::Accepted->value]);

        $attention = ManagerAttention::for($this->team);

        $this->assertSame([$ready->getKey()], $attention->readyToConfirm()->pluck('id')->all());
        $this->assertSame([$unstaffed->getKey()], $attention->unstaffed()->pluck('id')->all());
        $this->assertSame([$atRisk->getKey()], $attention->atRisk()->pluck('id')->all());
        $this->assertSame([$needsLead->getKey()], $attention->needsLead()->pluck('id')->all());
        $this->assertContains($atRisk->getKey(), $attention->needsStaffing()->pluck('id')->all());
        $this->assertContains($unstaffed->getKey(), $attention->needsStaffing()->pluck('id')->all());
        $this->assertSame(TentativeTriage::ReadyToConfirm, $attention->classify($ready->fresh()));
        $this->assertSame(TentativeTriage::Unstaffed, $attention->classify($unstaffed->fresh()));
        $this->assertSame(TentativeTriage::AtRisk, $attention->classify($atRisk->fresh()));
        $this->assertSame(TentativeTriage::NeedsLead, $attention->classify($needsLead->fresh()));
    }

    public function test_bounce_section_lists_flagged_members_for_the_tenant_only(): void
    {
        $this->actingAs($this->manager);

        $bounced = User::factory()->create([
            'name' => 'Bounced Member',
            'email_bounced_at' => now()->subDay(),
            'email_bounce_category' => 'recipient',
        ]);
        $this->team->grant($bounced, MembershipRole::Member);

        $otherTeam = Team::factory()->create();
        $outsider = User::factory()->create([
            'name' => 'Other Team Bounce',
            'email_bounced_at' => now()->subDay(),
        ]);
        $otherTeam->grant($outsider, MembershipRole::Member);

        $attention = ManagerAttention::for($this->team);
        $this->assertSame([$bounced->getKey()], $attention->bouncedMembers()->pluck('id')->all());

        Livewire::test(EmailBouncesWidget::class)
            ->assertSee('Bounced Member')
            ->assertDontSee('Other Team Bounce');
    }

    public function test_nav_badge_summary_excludes_ready_and_uses_highest_severity(): void
    {
        Booking::factory()->forTeam($this->team)->create([
            'requires_lead_instructor' => false,
            'start_at' => now()->addDays(20),
            'end_at' => now()->addDays(20)->addHours(2),
        ])->attendees()->attach($this->member->getKey(), ['status' => BookingAttendeeStatus::Accepted->value]);

        Booking::factory()->forTeam($this->team)->create([
            'requires_lead_instructor' => false,
            'start_at' => now()->addDays(30),
            'end_at' => now()->addDays(30)->addHours(2),
        ]);

        Booking::factory()->forTeam($this->team)->create([
            'requires_lead_instructor' => false,
            'start_at' => now()->addDays(5),
            'end_at' => now()->addDays(5)->addHours(2),
        ]);

        $summary = ManagerAttention::for($this->team)->summary();
        $this->assertSame(2, $summary['total']);
        $this->assertSame(AttentionSeverity::Uncertain, $summary['severity']);

        $bounced = User::factory()->create(['email_bounced_at' => now()]);
        $this->team->grant($bounced, MembershipRole::Member);
        ManagerAttention::flush($this->team->getKey());

        $summary = ManagerAttention::for($this->team)->summary();
        $this->assertSame(3, $summary['total']);
        $this->assertSame(AttentionSeverity::Destructive, $summary['severity']);

        Cache::put(ManagerAttention::cacheKey($this->team->getKey()), ['total' => 99, 'severity' => AttentionSeverity::Uncertain], 60);
        $this->assertSame(99, ManagerAttention::for($this->team)->summary()['total']);
        ManagerAttention::flush($this->team->getKey());
        $this->assertSame(3, ManagerAttention::for($this->team)->summary()['total']);
    }

    public function test_widgets_show_triage_sections(): void
    {
        $this->actingAs($this->manager);

        $ready = Booking::factory()->forTeam($this->team)->create([
            'activity_label' => 'Ready to confirm climb',
            'requires_lead_instructor' => false,
            'start_at' => now()->addDays(12),
            'end_at' => now()->addDays(12)->addHours(2),
        ]);
        $ready->attendees()->attach($this->member->getKey(), ['status' => BookingAttendeeStatus::Accepted->value]);

        $unstaffed = Booking::factory()->forTeam($this->team)->create([
            'activity_label' => 'Unstaffed climb',
            'requires_lead_instructor' => false,
            'start_at' => now()->addDays(3),
            'end_at' => now()->addDays(3)->addHours(2),
        ]);

        $needsLead = Booking::factory()->forTeam($this->team)->create([
            'activity_label' => 'Needs lead climb',
            'lead_needed_at' => now(),
            'start_at' => now()->addDays(2),
            'end_at' => now()->addDays(2)->addHours(2),
        ]);

        Livewire::test(ReadyToConfirmWidget::class)->assertSee('Ready to confirm climb');
        Livewire::test(NeedsStaffingWidget::class)
            ->assertSee('Unstaffed climb')
            ->assertSee('At risk');
        Livewire::test(NeedsLeadWidget::class)->assertSee('Needs lead climb');
    }
}
