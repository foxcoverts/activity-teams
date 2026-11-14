<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Filament\Admin\Pages\Dashboard as AdminDashboard;
use App\Filament\Admin\Resources\Activities\ActivityResource as AdminActivityResource;
use App\Filament\Admin\Resources\Teams\TeamResource as AdminTeamResource;
use App\Filament\Member\Pages\Dashboard as MemberDashboard;
use App\Filament\Member\Resources\Inbox\InboxResource;
use App\Filament\Teams\Resources\Bookings\BookingResource as TeamsBookingResource;
use App\Filament\Teams\Resources\Keys\KeyResource as TeamsKeyResource;
use App\Filament\Teams\Resources\NewsPosts\NewsPostResource as TeamsNewsPostResource;
use App\Models\Booking;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationIaTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_teams_exit_from_news_deep_links_to_team_news(): void
    {
        $team = Team::factory()->create();
        $member = User::factory()->create();
        $team->grant($member, MembershipRole::Member);

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        $newsUrl = TeamsNewsPostResource::getUrl(panel: 'teams', tenant: $team);

        $this->get('/news')
            ->assertOk()
            ->assertSee('href="'.$newsUrl.'"', false);
    }

    public function test_member_teams_exit_from_bookings_deep_links_to_team_bookings(): void
    {
        $team = Team::factory()->create();
        $member = User::factory()->create();
        $team->grant($member, MembershipRole::Member);

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        $bookingsUrl = TeamsBookingResource::getUrl(panel: 'teams', tenant: $team);

        $this->get('/bookings')
            ->assertOk()
            ->assertSee('href="'.$bookingsUrl.'"', false);
    }

    public function test_member_teams_exit_from_inbox_falls_back_to_team_dashboard(): void
    {
        $team = Team::factory()->create();
        $member = User::factory()->create();
        $team->grant($member, MembershipRole::Member);

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        $dashboardUrl = Filament::getPanel('teams')->getUrl($team);
        $newsUrl = TeamsNewsPostResource::getUrl(panel: 'teams', tenant: $team);

        $this->get(InboxResource::getUrl(panel: 'member'))
            ->assertOk()
            ->assertSee('href="'.$dashboardUrl.'"', false)
            ->assertDontSee('href="'.$newsUrl.'"', false);
    }

    public function test_teams_dashboard_href_stays_on_the_team_while_home_leaves(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $homeUrl = MemberDashboard::getUrl(panel: 'member');
        $dashboardUrl = Filament::getPanel('teams')->getUrl($team);

        $this->actingAs($manager)
            ->get('/teams/'.$team->slug)
            ->assertOk()
            ->assertSee('href="'.$dashboardUrl.'"', false)
            ->assertSee('href="'.$homeUrl.'"', false);
    }

    public function test_tenant_switcher_from_news_keeps_news_on_the_other_team(): void
    {
        $first = Team::factory()->create(['name' => 'First']);
        $second = Team::factory()->create(['name' => 'Second']);
        $manager = User::factory()->teamManager($first)->teamManager($second)->create();

        $secondNewsUrl = TeamsNewsPostResource::getUrl(panel: 'teams', tenant: $second);

        $this->actingAs($manager)
            ->get('/teams/'.$first->slug.'/news')
            ->assertOk()
            ->assertSee('href="'.$secondNewsUrl.'"', false);
    }

    public function test_tenant_switcher_from_booking_view_goes_to_other_team_bookings_index(): void
    {
        $first = Team::factory()->create(['name' => 'First']);
        $second = Team::factory()->create(['name' => 'Second']);
        $manager = User::factory()->teamManager($first)->teamManager($second)->create();
        $booking = Booking::factory()->forTeam($first)->create();

        $secondBookingsUrl = TeamsBookingResource::getUrl(panel: 'teams', tenant: $second);
        $secondBookingViewUrl = TeamsBookingResource::getUrl(
            name: 'view',
            parameters: ['record' => $booking],
            panel: 'teams',
            tenant: $second,
        );

        $this->actingAs($manager)
            ->get('/teams/'.$first->slug.'/bookings/'.$booking->getKey())
            ->assertOk()
            ->assertSee('href="'.$secondBookingsUrl.'"', false)
            ->assertDontSee('href="'.$secondBookingViewUrl.'"', false);
    }

    public function test_admin_teams_exit_from_activities_deep_links_when_manageable(): void
    {
        $team = Team::factory()->create();
        $admin = User::factory()->systemAdmin()->create();

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin)
            ->get('/admin/activities')
            ->assertOk()
            ->assertSee('/teams/'.$team->slug.'/activities', false);
    }

    public function test_admin_teams_exit_from_locks_deep_links_to_team_keys(): void
    {
        $team = Team::factory()->create();
        $admin = User::factory()->systemAdmin()->create();
        $keysUrl = TeamsKeyResource::getUrl(panel: 'teams', tenant: $team);

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin)
            ->get('/admin/locks')
            ->assertOk()
            ->assertSee('href="'.$keysUrl.'"', false);
    }

    public function test_teams_admin_exit_from_activities_deep_links_to_admin_activities(): void
    {
        $team = Team::factory()->create();
        $admin = User::factory()->systemAdmin()->create();
        $activitiesUrl = AdminActivityResource::getUrl(panel: 'admin');

        $this->actingAs($admin)
            ->get('/teams/'.$team->slug.'/activities')
            ->assertOk()
            ->assertSee('href="'.$activitiesUrl.'"', false);
    }

    public function test_teams_admin_exit_without_twin_opens_current_team_in_admin(): void
    {
        $team = Team::factory()->create();
        $admin = User::factory()->systemAdmin()->create();
        $editUrl = AdminTeamResource::getUrl(
            name: 'edit',
            parameters: ['record' => $team],
            panel: 'admin',
        );

        $this->actingAs($admin)
            ->get('/teams/'.$team->slug)
            ->assertOk()
            ->assertSee('href="'.$editUrl.'"', false);
    }

    public function test_member_admin_exit_falls_back_to_admin_dashboard(): void
    {
        $team = Team::factory()->create();
        $admin = User::factory()->systemAdmin()->create();
        $team->grant($admin, MembershipRole::Member);
        $dashboardUrl = AdminDashboard::getUrl(panel: 'admin');

        Filament::setCurrentPanel('member');
        $this->actingAs($admin)
            ->get('/bookings')
            ->assertOk()
            ->assertSee('href="'.$dashboardUrl.'"', false);
    }
}
