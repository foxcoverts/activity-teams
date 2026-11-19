<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountRole;
use App\Enums\MembershipRole;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_the_user_list_is_sorted_by_name(): void
    {
        $admin = User::factory()->systemAdmin()->create(['name' => 'Casey Admin']);
        $zebra = User::factory()->create(['name' => 'Zebra']);
        $alex = User::factory()->create(['name' => 'Alex']);

        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$alex, $admin, $zebra], inOrder: true);
    }

    public function test_the_roles_filter_matches_team_or_system_roles_and_none(): void
    {
        $team = Team::factory()->create();
        $admin = User::factory()->systemAdmin()->create(['name' => 'Admin']);
        $manager = User::factory()->teamManager($team)->create(['name' => 'Manager']);
        $member = User::factory()->teamMember($team)->create(['name' => 'Member']);
        $parent = User::factory()->create(['name' => 'Parent']);
        $team->grant($parent, MembershipRole::Parent);
        $oversight = User::factory()->oversight()->create(['name' => 'Oversight']);
        $none = User::factory()->create(['name' => 'None']);

        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->filterTable('roles', [MembershipRole::Manager->value])
            ->assertCanSeeTableRecords([$manager])
            ->assertCanNotSeeTableRecords([$admin, $member, $parent, $oversight, $none])
            ->filterTable('roles', [AccountRole::SystemAdmin->value])
            ->assertCanSeeTableRecords([$admin])
            ->assertCanNotSeeTableRecords([$manager, $member, $parent, $oversight, $none])
            ->filterTable('roles', [AccountRole::Oversight->value])
            ->assertCanSeeTableRecords([$oversight])
            ->assertCanNotSeeTableRecords([$admin, $manager, $member, $parent, $none])
            ->filterTable('roles', ['__empty'])
            ->assertCanSeeTableRecords([$none])
            ->assertCanNotSeeTableRecords([$admin, $manager, $member, $parent, $oversight]);
    }

    public function test_the_roles_filter_matches_any_selected_role(): void
    {
        $team = Team::factory()->create();
        $admin = User::factory()->systemAdmin()->create(['name' => 'Admin']);
        $manager = User::factory()->teamManager($team)->create(['name' => 'Manager']);
        $member = User::factory()->teamMember($team)->create(['name' => 'Member']);
        $none = User::factory()->create(['name' => 'None']);

        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->filterTable('roles', [
                MembershipRole::Manager->value,
                AccountRole::SystemAdmin->value,
            ])
            ->assertCanSeeTableRecords([$admin, $manager])
            ->assertCanNotSeeTableRecords([$member, $none])
            ->filterTable('roles', [
                MembershipRole::Member->value,
                '__empty',
            ])
            ->assertCanSeeTableRecords([$member, $none])
            ->assertCanNotSeeTableRecords([$admin, $manager]);
    }
}
