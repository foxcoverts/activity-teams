<?php

namespace Tests\Feature\Teams;

use App\Enums\AccountStatus;
use App\Enums\AgeGroup;
use App\Enums\MembershipRole;
use App\Filament\Teams\Resources\Members\Pages\ListMembers;
use App\Filament\Teams\Resources\Members\Pages\ViewMember;
use App\Models\TeamInvitation;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberDirectoryDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('teams');
    }

    public function test_the_members_list_shows_account_status_and_team_roles(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $member = User::factory()->teamMember($team)->create();
        $suspended = User::factory()->teamMember($team)->create(['suspended' => true]);
        $deleted = User::factory()->teamMember($team)->deleted()->create();
        $deleted = User::withTrashed()->findOrFail($deleted->getKey());
        $invited = User::factory()->placeholder()->create();
        $team->grant($invited, MembershipRole::Member);
        TeamInvitation::factory()->create([
            'team_id' => $team->getKey(),
            'user_id' => $invited->getKey(),
        ]);

        $this->actingAs($manager);

        Livewire::test(ListMembers::class)
            ->assertCanSeeTableRecords([$manager, $member, $suspended, $deleted, $invited])
            ->assertTableColumnStateSet('status', AccountStatus::Active, $member)
            ->assertTableColumnStateSet('status', AccountStatus::Suspended, $suspended)
            ->assertTableColumnStateSet('status', AccountStatus::Deleted, $deleted)
            ->assertTableColumnStateSet('status', AccountStatus::Invited, $invited)
            ->assertTableColumnStateSet('membership_role', MembershipRole::Manager, $manager)
            ->assertTableColumnStateSet('membership_role', MembershipRole::Member, $member);
    }

    public function test_the_member_view_shows_active_status_and_the_current_team_role(): void
    {
        $manager = User::factory()->teamManager()->create();

        $this->actingAs($manager);

        Livewire::test(ViewMember::class, ['record' => $manager->getKey()])
            ->assertSchemaStateSet([
                'status_badges' => [AccountStatus::Active],
                'membership_role' => MembershipRole::Manager,
            ]);
    }

    public function test_the_member_view_shows_suspended_instead_of_active(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $suspended = User::factory()->teamMember($team)->create(['suspended' => true]);

        $this->actingAs($manager);

        Livewire::test(ViewMember::class, ['record' => $suspended->getKey()])
            ->assertSchemaStateSet([
                'status_badges' => [AccountStatus::Suspended],
                'membership_role' => MembershipRole::Member,
            ]);
    }

    public function test_the_member_view_shows_under18_status_for_under18_members(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();

        $under18 = User::factory()->teamMember($team)->underEighteen()->create();

        $this->actingAs($manager);

        Livewire::test(ViewMember::class, ['record' => $under18->getKey()])
            ->assertSuccessful()
            ->assertSchemaStateSet([
                'status_badges' => [AccountStatus::Active, AgeGroup::Under18],
                'membership_role' => MembershipRole::Member,
            ]);
    }
}
