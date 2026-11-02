<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Filament\Admin\Resources\Users\Pages\ViewUser;
use App\Filament\Manage\Resources\Members\Pages\ViewMember;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserUnder18Test extends TestCase
{
    use RefreshDatabase;

    public function test_under_18_defaults_to_false_and_casts_to_boolean(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->isUnder18());
        $this->assertSame(false, $user->under_18);
        $this->assertTrue(User::factory()->underEighteen()->create()->isUnder18());
    }

    public function test_team_role_reports_the_highest_current_membership_role(): void
    {
        $user = User::factory()->create();
        $parentTeam = Team::factory()->create();
        $memberTeam = Team::factory()->create();
        $managerTeam = Team::factory()->create();

        $parentTeam->grant($user, MembershipRole::Parent);

        $this->assertSame(MembershipRole::Parent, $user->teamRole());
        $this->assertFalse(MembershipRole::Parent->isManager());

        $memberTeam->grant($user, MembershipRole::Member);

        $this->assertSame(MembershipRole::Member, $user->fresh()->teamRole());

        $managerTeam->grant($user, MembershipRole::Manager);

        $this->assertSame(MembershipRole::Manager, $user->fresh()->teamRole());
        $this->assertTrue(MembershipRole::Manager->isManager());
    }

    public function test_member_and_admin_profiles_show_the_under_18_badge_only_when_set(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $youngPerson = User::factory()->underEighteen()->create();
        $adult = User::factory()->create();
        $team->grant($youngPerson, MembershipRole::Member);
        $team->grant($adult, MembershipRole::Member);

        Filament::setCurrentPanel(Filament::getPanel('manage'));
        $this->actingAs($manager);
        Filament::setTenant($team);

        Livewire::test(ViewMember::class, ['record' => $youngPerson->getKey()])
            ->assertSee('Under 18');
        Livewire::test(ViewMember::class, ['record' => $adult->getKey()])
            ->assertDontSee('Under 18');

        $admin = User::factory()->systemAdmin()->create();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        Livewire::test(ViewUser::class, ['record' => $youngPerson->getKey()])
            ->assertSee('Under 18');
        Livewire::test(ViewUser::class, ['record' => $adult->getKey()])
            ->assertDontSee('Under 18');
    }
}
