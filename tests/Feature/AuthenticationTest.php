<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeStatus;
use App\Enums\MembershipRole;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_displayed(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_login_page_does_not_offer_registration(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('sign up for an account')
            ->assertDontSee(route('filament.member.auth.register'), escape: false);
    }

    public function test_team_member_can_open_bookings_list(): void
    {
        $user = User::factory()->teamMember()->create();

        $this->actingAs($user)
            ->get('/bookings')
            ->assertOk();
    }

    public function test_team_manager_sees_manage_link_in_members_sidebar(): void
    {
        $manager = User::factory()->teamManager()->create();
        $lastManagedTeam = Team::factory()->create(['name' => 'Archery']);
        $lastManagedTeam->grant($manager, MembershipRole::Manager);

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($manager);

        $this->get('/bookings')
            ->assertOk()
            ->assertSeeTextInOrder(['Dashboard', 'Manage Team', 'Team'])
            ->assertSee('/manage/'.$lastManagedTeam->slug, false);
    }

    public function test_team_member_does_not_see_manage_or_admin_links_in_members_sidebar(): void
    {
        $member = User::factory()->teamMember()->create();

        $this->actingAs($member)
            ->get('/bookings')
            ->assertOk()
            ->assertSeeTextInOrder([
                'Dashboard',
                'Team',
                'Bookings',
                'Documents',
                'News',
                'Keys',
                'Personal',
                'Inbox',
                'Qualifications',
            ])
            ->assertDontSee('Manage')
            ->assertDontSee('Admin')
            ->assertDontSee('href="/manage', false)
            ->assertDontSee(Filament::getPanel('admin')->getUrl(), false);
    }

    public function test_team_member_cannot_open_manage_panel(): void
    {
        $user = User::factory()->teamMember()->create();

        $this->actingAs($user)
            ->get('/manage')
            ->assertForbidden();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rolesWithoutAdminAccess(): array
    {
        return [
            'team member' => ['teamMember'],
            'team manager' => ['teamManager'],
        ];
    }

    #[DataProvider('rolesWithoutAdminAccess')]
    public function test_roles_without_site_access_cannot_open_the_admin_panel(string $state): void
    {
        $user = User::factory()->{$state}()->create();

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_a_team_manager_can_open_the_manage_panel_without_site_admin_access(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();

        $this->actingAs($manager)
            ->get('/manage')
            ->assertRedirect('/manage/'.$team->slug);

        $this->get('/manage/'.$team->slug)
            ->assertOk()
            ->assertDontSee(Filament::getPanel('admin')->getUrl(), false);
    }

    public function test_oversight_can_open_admin_and_not_manage(): void
    {
        $team = Team::factory()->create();
        $oversight = User::factory()->oversight()->create();

        $this->actingAs($oversight)
            ->get('/admin')
            ->assertOk();

        $this->get('/manage')
            ->assertForbidden();
    }

    public function test_a_system_admin_can_open_the_admin_panel(): void
    {
        $admin = User::factory()->systemAdmin()->create();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function suspendedSiteAccessRoles(): array
    {
        return [
            'system admin' => ['systemAdmin'],
            'oversight' => ['oversight'],
        ];
    }

    #[DataProvider('suspendedSiteAccessRoles')]
    public function test_a_suspended_person_cannot_open_the_admin_panel(string $flag): void
    {
        $user = User::factory()->{$flag}()->suspended()->create();

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_unauthenticated_admin_and_manage_requests_redirect_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('filament.admin.auth.login'));
        $this->get('/manage')->assertRedirect(route('filament.manage.auth.login'));
    }

    public function test_oversight_sees_the_admin_link_in_the_members_sidebar(): void
    {
        $oversight = User::factory()->oversight()->create();

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($oversight);

        $this->get('/')
            ->assertOk()
            ->assertSee('Admin')
            ->assertDontSee('Manage')
            ->assertSee(Filament::getPanel('admin')->getUrl(), false);
    }

    public function test_a_team_manager_does_not_see_the_admin_link_in_the_members_sidebar(): void
    {
        $manager = User::factory()->teamManager()->create();

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($manager);

        $this->get('/bookings')
            ->assertOk()
            ->assertSee('Manage Team')
            ->assertDontSee('Admin')
            ->assertDontSee(Filament::getPanel('admin')->getUrl(), false);
    }

    public function test_manage_sidebar_links_to_admin_for_a_system_admin(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $team = Team::factory()->create();

        $this->actingAs($admin)
            ->get('/manage/'.$team->slug)
            ->assertOk()
            ->assertSee(Filament::getPanel('admin')->getUrl(), false);
    }

    public function test_a_system_admin_who_manages_a_team_opens_that_team_from_manage(): void
    {
        Team::factory()->create(['name' => 'Alpha']);
        $zebra = Team::factory()->create(['name' => 'Zebra']);
        $admin = User::factory()->systemAdmin()->teamManager($zebra)->create();

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($admin);

        $this->get('/bookings')
            ->assertOk()
            ->assertSee('/manage/'.$zebra->slug, false);

        $this->get('/manage')
            ->assertRedirect('/manage/'.$zebra->slug);
    }

    public function test_a_system_admin_keeps_a_remembered_unmanaged_team(): void
    {
        $alpha = Team::factory()->create(['name' => 'Alpha']);
        $zebra = Team::factory()->create(['name' => 'Zebra']);
        $admin = User::factory()->systemAdmin()->teamManager($zebra)->create();

        $this->actingAs($admin)
            ->get('/manage/'.$alpha->slug)
            ->assertOk();

        $this->get('/manage')
            ->assertRedirect('/manage/'.$alpha->slug);

        Filament::setCurrentPanel(Filament::getPanel('member'));

        $this->get('/bookings')
            ->assertOk()
            ->assertSee('/manage/'.$alpha->slug, false);
    }

    public function test_admin_sidebar_links_to_manage_and_members(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $team = Team::factory()->create();
        $membersUrl = Filament::getPanel('member')->getUrl();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee($membersUrl, false)
            ->assertSee('/manage/'.$team->slug, false);
    }

    public function test_manage_bookings_index_loads_with_attendees(): void
    {
        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->teamMember()->create();
        $booking = Booking::factory()->create();
        $booking->attendees()->attach($member->id, [
            'status' => BookingAttendeeStatus::NeedsAction->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        $team = $manager->managedTeams()->first();

        $this->actingAs($manager)
            ->get('/manage/'.$team->slug.'/bookings')
            ->assertOk();
    }

    public function test_team_manager_can_open_manage_panel(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();

        $this->actingAs($manager)
            ->get('/manage')
            ->assertRedirect('/manage/'.$team->slug);
    }

    public function test_manage_sidebar_links_back_to_members_panel(): void
    {
        $manager = User::factory()->teamManager()->create();
        $admin = User::factory()->systemAdmin()->create();
        $team = $manager->managedTeams()->first();
        $membersUrl = Filament::getPanel('member')->getUrl();

        $this->actingAs($manager)
            ->get('/manage/'.$team->slug)
            ->assertOk()
            ->assertSeeTextInOrder(['Dashboard', 'Manage Team', 'Team', 'Members'])
            ->assertDontSee('Admin', false)
            ->assertSee('href="'.$membersUrl.'"', false);

        $this->actingAs($admin)
            ->get('/manage/'.$team->slug)
            ->assertOk()
            ->assertSeeTextInOrder(['Dashboard', 'Manage Team', 'Admin', 'Team'])
            ->assertSee('href="'.$membersUrl.'"', false);
    }

    public function test_a_system_admin_without_a_team_does_not_see_manage(): void
    {
        $admin = User::factory()->systemAdmin()->create();

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($admin)
            ->get('/')
            ->assertOk()
            ->assertSee('Admin')
            ->assertDontSee('Manage Team');

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('Manage Team', false);
    }

    public function test_admin_sidebar_links_back_to_members_panel(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $oversight = User::factory()->oversight()->create();
        Team::factory()->create();
        $membersUrl = Filament::getPanel('member')->getUrl();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSeeTextInOrder(['Dashboard', 'Manage Team', 'Admin', 'Organisation'])
            ->assertSee('href="'.$membersUrl.'"', false);

        $this->actingAs($oversight)
            ->get('/admin')
            ->assertOk()
            ->assertSeeTextInOrder(['Dashboard', 'Admin', 'Organisation'])
            ->assertDontSee('Manage Team', false)
            ->assertSee('href="'.$membersUrl.'"', false);
    }
}
