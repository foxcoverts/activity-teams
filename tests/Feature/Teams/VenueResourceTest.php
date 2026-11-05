<?php

namespace Tests\Feature\Teams;

use App\Filament\Teams\Resources\Venues\Pages\CreateVenue;
use App\Filament\Teams\Resources\Venues\Pages\EditVenue;
use App\Filament\Teams\Resources\Venues\Pages\ListVenues;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VenueResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('teams'));
    }

    public function test_the_venue_list_is_sorted_by_name(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $zebra = Venue::factory()->create(['name' => 'Zebra']);
        $archery = Venue::factory()->create(['name' => 'Archery']);
        $climbing = Venue::factory()->create(['name' => 'Climbing']);

        $this->actingAs($manager);
        Filament::setTenant($team);

        Livewire::test(ListVenues::class)
            ->assertCanSeeTableRecords([$archery, $climbing, $zebra], inOrder: true);
    }

    public function test_a_manager_can_create_a_catalog_venue_for_their_team(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();

        $this->actingAs($manager);
        Filament::setTenant($team);

        Livewire::test(CreateVenue::class)
            ->fillForm([
                'name' => 'New Team Hall',
                'street' => '10 New Road',
                'city' => 'Anytown',
            ])
            ->goToNextWizardStep()
            ->assertSeeText('No similar venues were found')
            ->goToNextWizardStep()
            ->assertSeeText('New venue to create')
            ->assertSeeText('Create venue')
            ->call('create')
            ->assertHasNoFormErrors();

        $venue = Venue::query()->where('name', 'New Team Hall')->firstOrFail();

        $this->assertTrue($team->venues()->whereKey($venue->getKey())->exists());
        $this->assertNull($venue->booking_id);
    }

    public function test_the_venue_creation_wizard_can_review_and_return_to_a_similar_venue(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $venue = Venue::factory()->create([
            'name' => 'Similar Hall',
            'street' => '1 Example Road',
            'notes' => 'Use the side entrance.',
        ]);

        $this->actingAs($manager);
        Filament::setTenant($team);

        Livewire::test(CreateVenue::class)
            ->assertSeeText('Create venue')
            ->fillForm([
                'name' => 'Similar Hall',
                'street' => '1 Example Road',
            ])
            ->goToNextWizardStep()
            ->assertSchemaComponentExists(
                'similar_venue_id',
                checkComponentUsing: fn (Radio $radio): bool => ! $radio->isInline()
                    && isset($radio->getOptions()[$venue->getKey()]),
            )
            ->assertSeeText('Similar Hall')
            ->fillForm([
                'similar_venue_id' => $venue->getKey(),
            ])
            ->goToNextWizardStep()
            ->assertSeeText('1 Example Road')
            ->assertSeeText('Use the side entrance.')
            ->assertSeeText('Use venue')
            ->goToPreviousWizardStep()
            ->assertSeeText('Similar Hall')
            ->assertFormFieldExists('similar_venue_id')
            ->goToPreviousWizardStep()
            ->assertFormFieldExists('name');
    }

    public function test_choosing_the_new_venue_option_clears_a_selected_similar_venue_and_creates_it(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $venue = Venue::factory()->create([
            'name' => 'Similar Hall',
            'postcode' => 'AB1 2CD',
        ]);

        $this->actingAs($manager);
        Filament::setTenant($team);

        Livewire::test(CreateVenue::class)
            ->fillForm([
                'name' => 'Similar Hall Annexe',
                'postcode' => 'AB1 2CD',
            ])
            ->goToNextWizardStep()
            ->set('data.similar_venue_id', $venue->getKey())
            ->assertFormSet(['similar_venue_id' => $venue->getKey()])
            ->set('data.use_new_venue', '1')
            ->assertFormSet(['similar_venue_id' => null])
            ->goToNextWizardStep()
            ->assertSeeText('New venue to create')
            ->assertSeeText('Create venue')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('venues', ['name' => 'Similar Hall Annexe']);
    }

    public function test_a_manager_creates_venues_through_the_wizard_without_the_give_action(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();

        $this->actingAs($manager);
        Filament::setTenant($team);

        Livewire::test(ListVenues::class)
            ->assertActionVisible('create')
            ->assertActionHidden('give');
    }

    public function test_a_system_admin_can_give_a_venue_from_a_searchable_list(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $team = Team::factory()->create();
        $venue = Venue::factory()->create();

        $this->actingAs($admin);
        Filament::setTenant($team);

        Livewire::test(ListVenues::class)
            ->assertActionVisible('give')
            ->mountAction('give')
            ->assertSchemaComponentExists('venue_id', checkComponentUsing: fn (Select $select): bool => $select->isSearchable());

        Livewire::test(ListVenues::class)
            ->callAction('give', data: ['venue_id' => $venue->getKey()]);

        $this->assertTrue($team->venues()->whereKey($venue->getKey())->exists());
    }

    public function test_a_manager_can_edit_a_catalog_venue_used_only_by_their_team(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $venue = Venue::factory()->create(['name' => 'Team Hall']);
        $team->addVenue($venue);

        $this->actingAs($manager);
        Filament::setTenant($team);

        Livewire::test(EditVenue::class, ['record' => $venue->getKey()])
            ->fillForm(['name' => 'Updated Team Hall'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('venues', [
            'id' => $venue->getKey(),
            'name' => 'Updated Team Hall',
        ]);
    }

    public function test_shared_catalog_venue_cannot_be_edited_by_a_manager(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $venue = Venue::factory()->create(['name' => 'Shared Hall']);
        $otherTeam = Team::factory()->create();
        $otherTeam->addVenue($venue);

        $this->actingAs($manager);
        Filament::setTenant($team);

        $this->assertFalse($manager->can('update', $venue));

        Livewire::test(ListVenues::class)
            ->assertTableActionVisible('edit', $venue)
            ->assertTableActionDisabled('edit', $venue)
            ->assertTableActionExists(
                'edit',
                fn (Action $action): bool => $action->getTooltip()
                    === 'This venue is shared by more than one team. Contact the site admin to make changes.',
                $venue,
            );
    }

    public function test_a_manager_can_remove_a_catalog_venue_without_deleting_it(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $venue = Venue::factory()->create(['name' => 'Removed Team Hall']);

        $this->actingAs($manager);
        Filament::setTenant($team);

        Livewire::test(ListVenues::class)
            ->callTableAction('remove', $venue);

        $this->assertFalse($team->venues()->whereKey($venue->getKey())->exists());
        $this->assertModelExists($venue);
    }
}
