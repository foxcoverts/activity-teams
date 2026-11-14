<?php

namespace Tests\Feature;

use App\Actions\CompleteDebriefAction;
use App\Actions\ConfirmAttendanceAction;
use App\Enums\BookingAttendeeStatus;
use App\Enums\TeamFeature;
use App\Filament\Teams\Resources\EquipmentSetGroups\Pages\ManageEquipmentSetGroups;
use App\Filament\Teams\Resources\EquipmentSets\Pages\CreateEquipmentSet;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\EquipmentSet;
use App\Models\EquipmentSetGroup;
use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Fieldset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class EquipmentSetGroupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('teams');
    }

    public function test_enabling_debrief_creates_default_equipment_group(): void
    {
        $team = Team::factory()->create();

        $this->assertSame(0, $team->equipmentSetGroups()->count());

        $team->enableFeature(TeamFeature::Debrief, now()->subDay());
        $team->save();

        $group = $team->fresh()->equipmentSetGroups()->first();
        $this->assertNotNull($group);
        $this->assertSame(EquipmentSetGroup::DEFAULT_LABEL, $group->label);
        $this->assertSame(1, $group->rank);
    }

    public function test_manager_can_create_group_and_reorder(): void
    {
        [$manager, $team] = $this->managerWithDebrief();

        $this->actingAs($manager);
        Filament::setTenant($team);

        $default = EquipmentSetGroup::ensureDefaultFor($team);

        Livewire::test(ManageEquipmentSetGroups::class)
            ->callAction('create', data: [
                'label' => 'Ropes',
            ])
            ->assertHasNoActionErrors();

        $ropes = EquipmentSetGroup::query()
            ->where('team_id', $team->getKey())
            ->where('label', 'Ropes')
            ->first();
        $this->assertNotNull($ropes);
        $this->assertSame(2, $ropes->rank);

        $ropes->moveUp();
        $this->assertSame(1, $ropes->fresh()->rank);
        $this->assertSame(2, $default->fresh()->rank);

        $ropes->fresh()->moveDown();
        $this->assertSame(2, $ropes->fresh()->rank);
        $this->assertSame(1, $default->fresh()->rank);
    }

    public function test_cannot_delete_last_group(): void
    {
        [$manager, $team] = $this->managerWithDebrief();
        $default = EquipmentSetGroup::ensureDefaultFor($team);

        $this->actingAs($manager);
        Filament::setTenant($team);

        $this->assertFalse($manager->can('delete', $default));

        $this->expectException(ValidationException::class);
        $default->deleteAndReassign();
    }

    public function test_deleting_group_reassigns_sets_to_first_remaining(): void
    {
        [$manager, $team] = $this->managerWithDebrief();
        $default = EquipmentSetGroup::ensureDefaultFor($team);
        $second = EquipmentSetGroup::factory()->forTeam($team)->create([
            'label' => 'Belays',
            'rank' => 2,
        ]);
        $set = EquipmentSet::factory()->forTeam($team)->create([
            'name' => 'Dynamic rope',
            'equipment_set_group_id' => $second->getKey(),
        ]);

        $this->actingAs($manager);
        Filament::setTenant($team);

        $this->assertTrue($manager->can('delete', $second));

        Livewire::test(ManageEquipmentSetGroups::class)
            ->callTableAction('delete', $second)
            ->assertSuccessful();

        $this->assertDatabaseMissing('equipment_set_groups', ['id' => $second->getKey()]);
        $this->assertSame((string) $default->getKey(), (string) $set->fresh()->equipment_set_group_id);
        $this->assertSame(1, $default->fresh()->rank);
    }

    public function test_creating_equipment_set_defaults_to_first_group(): void
    {
        [$manager, $team] = $this->managerWithDebrief();
        $default = EquipmentSetGroup::ensureDefaultFor($team);
        EquipmentSetGroup::factory()->forTeam($team)->create([
            'label' => 'Later',
            'rank' => 2,
        ]);
        $activity = Activity::factory()->create();
        $team->addActivity($activity);

        $this->actingAs($manager);
        Filament::setTenant($team);

        Livewire::test(CreateEquipmentSet::class)
            ->fillForm([
                'name' => 'Belay kit',
                'equipment_set_group_id' => $default->getKey(),
                'activities' => [$activity->getKey()],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $set = EquipmentSet::query()->where('name', 'Belay kit')->first();
        $this->assertNotNull($set);
        $this->assertSame((string) $default->getKey(), (string) $set->equipment_set_group_id);
    }

    public function test_debrief_saves_equipment_sets_selected_across_groups(): void
    {
        $lead = User::factory()->teamMember()->create();
        [$manager, $team] = $this->managerWithDebrief();
        $default = EquipmentSetGroup::ensureDefaultFor($team);
        $ropes = EquipmentSetGroup::factory()->forTeam($team)->create([
            'label' => 'Ropes',
            'rank' => 2,
        ]);

        $booking = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'start_at' => now()->subHours(4),
            'end_at' => now()->subHours(2),
            'lead_instructor_id' => $lead->getKey(),
        ]);

        $helmet = EquipmentSet::factory()->forTeam($team)->create([
            'name' => 'Helmets',
            'equipment_set_group_id' => $default->getKey(),
        ]);
        $dynamic = EquipmentSet::factory()->forTeam($team)->create([
            'name' => 'Dynamic',
            'equipment_set_group_id' => $ropes->getKey(),
        ]);
        $helmet->activities()->attach($booking->activity_id);
        $dynamic->activities()->attach($booking->activity_id);

        $member = User::factory()->teamMember()->create();
        $booking->attendees()->syncWithoutDetaching([
            $member->getKey() => ['status' => BookingAttendeeStatus::Accepted],
        ]);

        $debrief = app(CompleteDebriefAction::class)($booking, $lead, [
            'hours' => 2,
            'participant_count' => 4,
            'weather' => 'Dry',
            'equipment_set_ids' => [
                (string) $helmet->getKey(),
                (string) $dynamic->getKey(),
            ],
            'marks' => [
                (string) $member->getKey() => ConfirmAttendanceAction::MARK_CONFIRM,
            ],
        ]);

        $this->assertTrue($debrief->equipmentSets->contains($helmet));
        $this->assertTrue($debrief->equipmentSets->contains($dynamic));
        $this->assertSame(
            [$default->label, $ropes->label],
            $team->equipmentSetGroups()->orderBy('rank')->pluck('label')->all(),
        );

        $grouped = (new \ReflectionClass(\App\Filament\Bookings\CompleteDebriefAction::class))
            ->getMethod('equipmentUsedSchema');
        $grouped->setAccessible(true);
        /** @var list<Fieldset> $fieldsets */
        $fieldsets = $grouped->invoke(null, $booking->fresh());

        $this->assertCount(2, $fieldsets);
        $this->assertSame($default->label, $fieldsets[0]->getLabel());
        $this->assertSame($ropes->label, $fieldsets[1]->getLabel());
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function managerWithDebrief(): array
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->teams()->first();
        assert($team instanceof Team);
        $team->enableFeature(TeamFeature::Debrief, now()->subDay());
        $team->save();

        return [$manager, $team->fresh()];
    }
}
