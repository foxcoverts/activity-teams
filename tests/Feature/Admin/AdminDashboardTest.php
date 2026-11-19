<?php

namespace Tests\Feature\Admin;

use App\Enums\AttentionSeverity;
use App\Enums\InboundMailOutcome;
use App\Enums\MembershipRole;
use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Admin\Resources\Activities\ActivityResource;
use App\Filament\Admin\Resources\Audiences\AudienceResource;
use App\Filament\Admin\Resources\Keys\KeyResource;
use App\Filament\Admin\Resources\Teams\TeamResource;
use App\Filament\Admin\Resources\Venues\VenueResource;
use App\Filament\Admin\Widgets\ActivitiesWithoutTeamsWidget;
use App\Filament\Admin\Widgets\AdminAllCaughtUpWidget;
use App\Filament\Admin\Widgets\AdminSnapshotWidget;
use App\Filament\Admin\Widgets\AudiencesWithoutActivitiesWidget;
use App\Filament\Admin\Widgets\InboundNeedsReviewWidget;
use App\Filament\Admin\Widgets\KeysWithoutLocksWidget;
use App\Filament\Admin\Widgets\PendingSiteInvitesWidget;
use App\Filament\Admin\Widgets\TeamsWithoutManagersWidget;
use App\Filament\Admin\Widgets\VenuesWithoutTeamsWidget;
use App\Filament\Navigation\CrossPanelLinks;
use App\Models\Activity;
use App\Models\Audience;
use App\Models\InboundMail;
use App\Models\Key;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Models\Venue;
use App\Support\AdminAttention;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_system_admin_sees_inbound_invites_and_teams_without_managers(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $this->actingAs($admin);

        InboundMail::factory()->create([
            'outcome' => InboundMailOutcome::Queued,
            'subject' => 'Review me please',
        ]);

        $invitee = User::factory()->placeholder()->create(['name' => 'Pending Invitee']);
        TeamInvitation::factory()->siteAccess()->create([
            'user_id' => $invitee->getKey(),
            'email' => 'pending-invitee@example.test',
        ]);

        Team::factory()->create(['name' => 'Orphan Admin Team']);

        $attention = AdminAttention::for($admin);
        $this->assertTrue($attention->includesInbound());
        $this->assertCount(1, $attention->inboundNeedingReview());
        $this->assertCount(1, $attention->pendingSiteInvites());
        $this->assertCount(1, $attention->teamsWithoutManagers());
        $this->assertSame(3, $attention->summary()['total']);
        $this->assertSame(AttentionSeverity::Uncertain, $attention->summary()['severity']);

        $this->get(Dashboard::getUrl(panel: 'admin'))->assertOk();

        Livewire::test(AdminSnapshotWidget::class)
            ->assertSeeTextInOrder(['At a glance', 'Teams', 'Activities', 'Venues', 'Audiences', 'Keys'])
            ->assertSeeHtml('href="'.TeamResource::getUrl('index').'"')
            ->assertSeeHtml('href="'.ActivityResource::getUrl('index').'"')
            ->assertSeeHtml('href="'.VenueResource::getUrl('index').'"')
            ->assertSeeHtml('href="'.AudienceResource::getUrl('index').'"')
            ->assertSeeHtml('href="'.KeyResource::getUrl('index').'"');
        Livewire::test(InboundNeedsReviewWidget::class)->assertSee('Review me please');
        Livewire::test(PendingSiteInvitesWidget::class)->assertSee('Pending Invitee');
        Livewire::test(TeamsWithoutManagersWidget::class)->assertSee('Orphan Admin Team');
        Livewire::test(AdminAllCaughtUpWidget::class)->assertDontSee(__('Nothing needs admin attention right now.'));
    }

    public function test_system_admin_sees_unconfigured_catalog_items(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $this->actingAs($admin);

        // Activity/Venue factories attach to the latest team when one exists.
        Activity::factory()->create(['name' => 'Lone Activity']);
        Venue::factory()->create(['name' => 'Lone Venue']);
        Audience::factory()->create(['name' => 'Lone Audience']);
        Key::factory()->create(['name' => 'Bare Key']);

        Team::factory()->create()->grant($admin, MembershipRole::Manager);

        $attention = AdminAttention::for($admin);
        $this->assertTrue($attention->includesCatalogTriage());
        $this->assertSame(4, $attention->catalogOrphanTotal());
        $this->assertSame(4, $attention->summary()['total']);

        Livewire::test(ActivitiesWithoutTeamsWidget::class)->assertSee('Lone Activity');
        Livewire::test(VenuesWithoutTeamsWidget::class)->assertSee('Lone Venue');
        Livewire::test(AudiencesWithoutActivitiesWidget::class)->assertSee('Lone Audience');
        Livewire::test(KeysWithoutLocksWidget::class)->assertSee('Bare Key');
    }

    public function test_triage_columns_pack_only_non_empty_widgets(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $this->actingAs($admin);

        Activity::factory()->create(['name' => 'Lone Activity']);
        $invitee = User::factory()->placeholder()->create(['name' => 'Packed Invitee']);
        TeamInvitation::factory()->siteAccess()->create([
            'user_id' => $invitee->getKey(),
            'email' => 'packed-invitee@example.test',
        ]);
        Team::factory()->create()->grant($admin, MembershipRole::Manager);

        [$left, $right] = (new ReflectionMethod(Dashboard::class, 'packTriageColumns'))
            ->invoke(new Dashboard);

        $packed = [...$left, ...$right];

        $this->assertSame(
            [PendingSiteInvitesWidget::class, ActivitiesWithoutTeamsWidget::class],
            $packed,
        );
        $this->assertNotEmpty($left);
        $this->assertNotEmpty($right);
    }

    public function test_oversight_sees_directory_triage_but_not_inbound_mail(): void
    {
        $oversight = User::factory()->oversight()->create();
        $admin = User::factory()->systemAdmin()->create();
        Team::factory()->create()->grant($admin, MembershipRole::Manager);

        InboundMail::factory()->create([
            'outcome' => InboundMailOutcome::Failed,
            'subject' => 'Secret inbound',
        ]);

        $invitee = User::factory()->placeholder()->create(['name' => 'Oversight Invitee']);
        TeamInvitation::factory()->siteAccess()->create([
            'user_id' => $invitee->getKey(),
            'email' => 'oversight-invitee@example.test',
        ]);

        Team::factory()->create(['name' => 'No Managers Yet']);

        $this->actingAs($oversight);

        $attention = AdminAttention::for($oversight);
        $this->assertFalse($attention->includesInbound());
        $this->assertCount(0, $attention->inboundNeedingReview());
        $this->assertCount(1, $attention->pendingSiteInvites());
        $this->assertCount(1, $attention->teamsWithoutManagers());
        $this->assertSame(2, $attention->summary()['total']);

        $this->get(Dashboard::getUrl(panel: 'admin'))->assertOk();

        $this->assertFalse(InboundNeedsReviewWidget::canView());
        $this->assertFalse(ActivitiesWithoutTeamsWidget::canView());
        Livewire::test(PendingSiteInvitesWidget::class)->assertSee('Oversight Invitee');
        Livewire::test(TeamsWithoutManagersWidget::class)->assertSee('No Managers Yet');
        Livewire::test(InboundNeedsReviewWidget::class)->assertDontSee('Secret inbound');
    }

    public function test_manager_without_admin_access_cannot_open_admin_dashboard(): void
    {
        $manager = User::factory()->create();
        Team::factory()->create()->grant($manager, MembershipRole::Manager);

        $this->actingAs($manager)
            ->get(Dashboard::getUrl(panel: 'admin'))
            ->assertForbidden();
    }

    public function test_navigation_badge_matches_visible_attention_total(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $this->actingAs($admin);

        InboundMail::factory()->create(['outcome' => InboundMailOutcome::Failed]);
        InboundMail::factory()->create(['outcome' => InboundMailOutcome::Queued]);

        $this->assertSame('2', Dashboard::getNavigationBadge());
        $this->assertSame(AttentionSeverity::Destructive->filamentColor(), Dashboard::getNavigationBadgeColor());
    }

    public function test_cross_panel_admin_link_reuses_dashboard_attention_badge(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $this->actingAs($admin);

        InboundMail::factory()->create(['outcome' => InboundMailOutcome::Failed]);
        InboundMail::factory()->create(['outcome' => InboundMailOutcome::Queued]);

        $item = CrossPanelLinks::admin();

        $this->assertSame(Dashboard::getNavigationBadge(), $item->getBadge());
        $this->assertSame(Dashboard::getNavigationBadgeColor(), $item->getBadgeColor());
        $this->assertSame('2', $item->getBadge());
        $this->assertSame(AttentionSeverity::Destructive->filamentColor(), $item->getBadgeColor());
    }

    public function test_all_caught_up_when_queues_are_empty(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        Team::factory()->create()->grant($admin, MembershipRole::Manager);
        $this->actingAs($admin);

        $this->assertSame(0, AdminAttention::for($admin)->summary()['total']);
        $this->assertTrue(AdminAttention::for($admin)->isCaughtUp());
        Livewire::test(AdminAllCaughtUpWidget::class)->assertSee(__('Nothing needs admin attention right now.'));
    }

    public function test_inbound_see_all_targets_needs_review_filter(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $this->actingAs($admin);

        InboundMail::factory()->count(AdminAttention::PREVIEW_LIMIT + 1)->create([
            'outcome' => InboundMailOutcome::Queued,
        ]);

        Livewire::test(InboundNeedsReviewWidget::class)
            ->assertSee('filters%5Bneeds_review%5D%5BisActive%5D=1', false)
            ->assertSee('filters%5Bneeds_review%5D%5Bvalue%5D=1', false);
    }

    public function test_pending_invites_see_all_targets_not_yet_accepted_filter(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $this->actingAs($admin);

        for ($i = 0; $i < AdminAttention::PREVIEW_LIMIT + 1; $i++) {
            $invitee = User::factory()->placeholder()->create(['name' => "Invitee {$i}"]);
            TeamInvitation::factory()->siteAccess()->create([
                'user_id' => $invitee->getKey(),
                'email' => "invitee-{$i}@example.test",
            ]);
        }

        Livewire::test(PendingSiteInvitesWidget::class)
            ->assertSee('filters%5Bnot_accepted%5D%5BisActive%5D=1', false);
    }
}
