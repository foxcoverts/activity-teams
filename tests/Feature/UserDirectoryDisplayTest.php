<?php

namespace Tests\Feature;

use App\Enums\AccountRole;
use App\Enums\AccountStatus;
use App\Enums\MembershipRole;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Filament\Admin\Resources\Users\Pages\ViewUser;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserDirectoryDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_users_list_status_column_shows_deleted_suspended_and_invitation_states(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $active = User::factory()->create();
        $suspended = User::factory()->suspended()->create();
        $deleted = User::factory()->deleted()->create();
        $invited = TeamInvitation::factory()->siteAccess(false, false)->create()->user;
        $expired = TeamInvitation::factory()->siteAccess(false, false)->expired()->create()->user;
        $declined = TeamInvitation::factory()->siteAccess(false, false)->declined()->create()->user;
        $notInvited = User::factory()->placeholder()->create();

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$active, $suspended, $deleted, $invited, $expired, $declined, $notInvited])
            ->assertTableColumnStateSet('status', AccountStatus::Active, $active)
            ->assertTableColumnStateSet('status', AccountStatus::Suspended, $suspended)
            ->assertTableColumnStateSet('status', AccountStatus::Deleted, $deleted)
            ->assertTableColumnStateSet('status', AccountStatus::Invited, $invited)
            ->assertTableColumnStateSet('status', AccountStatus::Expired, $expired)
            ->assertTableColumnStateSet('status', AccountStatus::Declined, $declined)
            ->assertTableColumnStateSet('status', AccountStatus::NotInvited, $notInvited);
    }

    public function test_the_users_list_roles_column_combines_oversight_and_system_admin(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $oversight = User::factory()->oversight()->create();
        $both = User::factory()->systemAdmin()->oversight()->create();
        $member = User::factory()->create();
        $suspendedAdmin = User::factory()->systemAdmin()->suspended()->create();

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->assertTableColumnStateSet('roles', [AccountRole::SystemAdmin], $admin)
            ->assertTableColumnStateSet('roles', [AccountRole::Oversight], $oversight)
            ->assertTableColumnStateSet('roles', [AccountRole::SystemAdmin, AccountRole::Oversight], $both)
            ->assertTableColumnStateSet('roles', null, $member)
            ->assertTableColumnStateSet('roles', null, $suspendedAdmin);
    }

    public function test_the_user_view_combines_status_team_role_and_access_badges(): void
    {
        $climbing = Team::factory()->create();
        $archery = Team::factory()->create();
        $admin = User::factory()->systemAdmin()->oversight()->teamManager($climbing)->teamMember($archery)->create();

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(ViewUser::class, ['record' => $admin->getKey()])
            ->assertSchemaStateSet([
                'status_badges' => [
                    AccountStatus::Active,
                    MembershipRole::Manager,
                    AccountRole::SystemAdmin,
                    AccountRole::Oversight,
                ],
            ]);
    }

    public function test_the_user_view_shows_a_member_badge_when_they_do_not_manage_a_team(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $member = User::factory()->teamMember()->create();

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(ViewUser::class, ['record' => $member->getKey()])
            ->assertSchemaStateSet([
                'status_badges' => [
                    AccountStatus::Active,
                    MembershipRole::Member,
                ],
            ]);
    }

    public function test_the_user_view_omits_a_team_badge_when_they_are_not_in_a_team(): void
    {
        $admin = User::factory()->systemAdmin()->create();

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(ViewUser::class, ['record' => $admin->getKey()])
            ->assertSchemaStateSet([
                'status_badges' => [
                    AccountStatus::Active,
                    AccountRole::SystemAdmin,
                ],
            ]);
    }

    public function test_the_user_view_shows_suspended_instead_of_active_and_keeps_the_team_badge(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $suspended = User::factory()->suspended()->create();

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(ViewUser::class, ['record' => $suspended->getKey()])
            ->assertSchemaStateSet([
                'status_badges' => [
                    AccountStatus::Suspended,
                    MembershipRole::Member,
                ],
            ]);
    }
}
