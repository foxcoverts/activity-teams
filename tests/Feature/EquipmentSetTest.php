<?php

namespace Tests\Feature;

use App\Enums\TeamFeature;
use App\Filament\Teams\Resources\EquipmentSets\EquipmentSetResource;
use App\Filament\Teams\Resources\EquipmentSets\Pages\CreateEquipmentSet;
use App\Filament\Teams\Resources\EquipmentSets\Pages\ListEquipmentSets;
use App\Models\Activity;
use App\Models\EquipmentSet;
use App\Models\EquipmentSetGroup;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EquipmentSetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('teams');
    }

    public function test_manager_can_create_equipment_set_when_debrief_enabled(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->teams()->first();
        assert($team instanceof Team);
        $team->enableFeature(TeamFeature::Debrief, now()->subDay());
        $team->save();

        $activity = Activity::factory()->create();

        $this->actingAs($manager);
        Filament::setTenant($team);

        $defaultGroup = EquipmentSetGroup::ensureDefaultFor($team);

        Livewire::test(CreateEquipmentSet::class)
            ->fillForm([
                'name' => 'Belay kit',
                'equipment_set_group_id' => $defaultGroup->getKey(),
                'activities' => [$activity->getKey()],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $set = EquipmentSet::query()->where('team_id', $team->getKey())->where('name', 'Belay kit')->first();
        $this->assertNotNull($set);
        $this->assertTrue($set->activities->contains($activity));
        $this->assertSame((string) $defaultGroup->getKey(), (string) $set->equipment_set_group_id);
    }

    public function test_equipment_sets_nav_hidden_without_debrief_feature(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->teams()->first();
        assert($team instanceof Team);

        $this->actingAs($manager);
        Filament::setTenant($team);

        $this->assertFalse(EquipmentSetResource::canViewAny());
        $this->assertFalse(EquipmentSetResource::canAccess());
    }

    public function test_system_admin_can_manage_sets_without_being_manager(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $manager = User::factory()->teamManager()->create();
        $team = $manager->teams()->first();
        assert($team instanceof Team);
        $team->enableFeature(TeamFeature::Debrief, now()->subDay());
        $team->save();

        $this->assertFalse($admin->manages($team));

        $this->actingAs($admin);
        Filament::setTenant($team);

        $this->assertTrue(EquipmentSetResource::canViewAny());

        Livewire::test(ListEquipmentSets::class)
            ->assertSuccessful();
    }
}
