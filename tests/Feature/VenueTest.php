<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Venues\Pages\CreateVenue;
use App\Filament\Admin\Resources\Venues\Pages\EditVenue;
use App\Filament\Admin\Resources\Venues\VenueResource as AdminVenueResource;
use App\Filament\Manage\Resources\Bookings\BookingResource;
use App\Filament\Manage\Resources\Bookings\Pages\ViewBooking as ManageViewBooking;
use App\Filament\Teams\Resources\Bookings\Pages\ViewBooking as TeamsViewBooking;
use App\Filament\Teams\Resources\Venues\Pages\CreateVenue as TeamsCreateVenue;
use App\Filament\Teams\Resources\Venues\VenueResource as TeamsVenueResource;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Support\Coords;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class VenueTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_venue_shows_address(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $venue = Venue::factory()->create([
            'name' => 'Sample Venue',
            'street' => '123 Main St',
            'city' => 'Sample City',
            'region' => 'Sample Region',
            'postcode' => '12345',
            'country' => 'GB',
        ]);

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        $this->get(AdminVenueResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Sample Venue');

        $this->get(AdminVenueResource::getUrl('view', ['record' => $venue->getKey()]))
            ->assertOk()
            ->assertSeeInOrder([
                'Address',
                '123 Main St',
                'Sample City',
                'Sample Region',
                '12345',
                'United Kingdom', // GB
            ]);
    }

    public function test_teams_venue_shows_address(): void
    {
        $manager = User::factory()->teamManager()->create();
        $venue = Venue::factory()->create([
            'name' => 'Sample Venue',
            'street' => '123 Main St',
            'city' => 'Sample City',
            'region' => 'Sample Region',
            'postcode' => '12345',
            'country' => 'GB',
        ]);

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);

        $this->get(TeamsVenueResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Sample Venue');

        $this->get(TeamsVenueResource::getUrl('view', ['record' => $venue->getKey()]))
            ->assertOk()
            ->assertSeeInOrder([
                'Address',
                '123 Main St',
                'Sample City',
                'Sample Region',
                '12345',
                'United Kingdom', // GB
            ]);
    }

    public function test_booking_shows_venue(): void
    {
        $manager = User::factory()->teamManager()->create();
        $venue = Venue::factory()->create([
            'name' => 'Sample Venue',
            'street' => '123 Main St',
            'city' => 'Sample City',
            'region' => 'Sample Region',
            'postcode' => '12345',
            'country' => 'GB',
        ]);

        $booking = Booking::factory()->create([
            'venue_id' => $venue->getKey(),
        ]);

        Filament::setCurrentPanel('manage');
        $this->actingAs($manager);

        $this->get(BookingResource::getUrl('view', ['record' => $booking->getKey()]))
            ->assertOk()
            ->assertSeeInOrder([
                'Location',
                'Sample Venue',
                '123 Main St',
                'Sample City',
                'Sample Region',
                '12345',
                'United Kingdom', // GB
                'Show 5 more',
            ]);
    }

    public function test_admin_can_create_a_venue_without_coordinates(): void
    {
        $admin = User::factory()->systemAdmin()->create();

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(CreateVenue::class)
            ->assertFormFieldHidden('map')
            ->fillForm([
                'name' => 'Unlocated Hall',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $venue = Venue::query()->where('name', 'Unlocated Hall')->first();

        $this->assertNotNull($venue);
        $this->assertSame('GB', $venue->country);
        $this->assertNull($venue->latitude);
        $this->assertNull($venue->longitude);
        $this->assertNull($venue->coords);
    }

    public function test_manager_cannot_create_a_catalog_venue_with_the_same_name_and_no_postcode(): void
    {
        $manager = User::factory()->teamManager()->create();
        Venue::factory()->create([
            'name' => 'Fox Hall',
            'postcode' => 'AB1 2CD',
            'city' => 'Anytown',
        ]);

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);

        Livewire::test(TeamsCreateVenue::class)
            ->assertSeeText('Venue details')
            ->assertSeeText('Similar venues')
            ->assertSeeText('Confirm venue')
            ->fillForm(['name' => 'fOX hALL'])
            ->call('create')
            ->assertHasFormErrors(['postcode'])
            ->assertSeeText('Add a postcode. "Fox Hall" already exists (Anytown, AB1 2CD).');

        $this->assertSame(1, Venue::query()->whereRaw('LOWER(name) = ?', ['fox hall'])->count());
    }

    public function test_catalog_name_conflict_stops_the_wizard_on_venue_details(): void
    {
        $manager = User::factory()->teamManager()->create();
        Venue::factory()->create([
            'name' => 'Fox Hall',
            'postcode' => 'AB1 2CD',
            'city' => 'Anytown',
        ]);

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);

        Livewire::test(TeamsCreateVenue::class)
            ->fillForm(['name' => 'Fox Hall'])
            ->goToNextWizardStep()
            ->assertHasFormErrors(['postcode'])
            ->assertSeeText('Add a postcode. "Fox Hall" already exists (Anytown, AB1 2CD).')
            ->assertSeeText('Venue details');

        $this->assertSame(1, Venue::query()->whereRaw('LOWER(name) = ?', ['fox hall'])->count());
    }

    public function test_manager_can_create_a_same_named_catalog_venue_at_a_different_postcode(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        Venue::factory()->create([
            'name' => 'Fox Hall',
            'postcode' => 'AB1 2CD',
        ]);

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);

        Livewire::test(TeamsCreateVenue::class)
            ->fillForm([
                'name' => 'Fox Hall',
                'postcode' => 'XY9 8ZZ',
                'city' => 'Othertown',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, Venue::query()->whereRaw('LOWER(name) = ?', ['fox hall'])->count());
        $this->assertTrue($team->venues()->where('venues.postcode', 'XY9 8ZZ')->exists());
    }

    public function test_a_manager_can_choose_a_similar_catalog_venue_instead_of_creating_a_duplicate(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $venue = Venue::factory()->create([
            'name' => 'Fox Hall',
            'street' => '1 Example Road',
            'city' => 'Anytown',
        ]);

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);

        Livewire::test(TeamsCreateVenue::class)
            ->fillForm([
                'name' => 'Fox Hall',
                'street' => '1 Example Road',
                'city' => 'Anytown',
                'similar_venue_id' => $venue->getKey(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Venue::query()->whereRaw('LOWER(name) = ?', ['fox hall'])->count());
        $this->assertTrue($team->venues()->whereKey($venue->getKey())->exists());
    }

    public function test_admin_wizard_lists_similar_venues_and_opens_the_chosen_one_instead_of_creating_a_duplicate(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $venue = Venue::factory()->create([
            'name' => 'Fox Hall',
            'postcode' => 'AB1 2CD',
        ]);

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(CreateVenue::class)
            ->fillForm([
                'name' => 'Fox Hall Annexe',
                'postcode' => 'AB1 2CD',
            ])
            ->goToNextWizardStep()
            ->assertSeeText('Fox Hall')
            ->set('data.similar_venue_id', $venue->getKey())
            ->goToNextWizardStep()
            ->assertSeeText('Open existing venue')
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(AdminVenueResource::getUrl('view', ['record' => $venue]));

        $this->assertSame(1, Venue::query()->count());
    }

    public function test_admin_can_create_a_new_venue_despite_similar_matches(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $venue = Venue::factory()->create([
            'name' => 'Fox Hall',
            'postcode' => 'AB1 2CD',
        ]);

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(CreateVenue::class)
            ->fillForm([
                'name' => 'Fox Hall Annexe',
                'postcode' => 'AB1 2CD',
            ])
            ->goToNextWizardStep()
            ->set('data.similar_venue_id', $venue->getKey())
            ->set('data.use_new_venue', '1')
            ->goToNextWizardStep()
            ->assertSeeText('Create venue')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('venues', ['name' => 'Fox Hall Annexe']);
        $this->assertSame(2, Venue::query()->count());
    }

    public function test_admin_cannot_create_a_catalog_venue_with_the_same_name_and_postcode(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        Venue::factory()->create([
            'name' => 'Fox Hall',
            'postcode' => 'AB1 2CD',
        ]);

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(CreateVenue::class)
            ->fillForm([
                'name' => 'fOX hALL',
                'postcode' => 'ab1 2cd',
            ])
            ->call('create')
            ->assertHasFormErrors(['postcode'])
            ->assertSeeText('A catalog venue named Fox Hall already exists at AB1 2CD.');

        $this->assertSame(1, Venue::query()->count());
    }

    public function test_catalog_venue_model_allows_a_postcoded_namesake_of_a_blank_existing_venue(): void
    {
        Venue::factory()->create(['name' => 'Fox Hall']);

        $venue = Venue::create([
            'name' => 'Fox Hall',
            'postcode' => 'AB1 2CD',
        ]);

        $this->assertSame('AB1 2CD', $venue->postcode);
        $this->assertSame(2, Venue::query()->whereRaw('LOWER(name) = ?', ['fox hall'])->count());
    }

    public function test_catalog_venue_model_rejects_saving_a_blank_namesake_after_a_postcoded_twin_exists(): void
    {
        $blank = Venue::factory()->create(['name' => 'Fox Hall']);
        Venue::factory()->create([
            'name' => 'Fox Hall',
            'postcode' => 'AB1 2CD',
        ]);

        try {
            $blank->notes = 'Updated notes';
            $blank->save();
            $this->fail('A blank namesake was saved after a postcoded twin existed.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Add a postcode. "Fox Hall" already exists (AB1 2CD).',
                $exception->errors()['postcode'][0],
            );
        }
    }

    public function test_catalog_venue_model_rejects_two_blank_postcode_namesakes(): void
    {
        Venue::factory()->create(['name' => 'Fox Hall']);

        try {
            Venue::create(['name' => ' fOX hALL ']);
            $this->fail('A duplicate blank catalog venue name was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Add a postcode. "Fox Hall" already exists without a postcode.',
                $exception->errors()['postcode'][0],
            );
        }

        $this->assertSame(1, Venue::query()->count());
    }

    public function test_venue_option_label_uses_the_best_available_place_hint(): void
    {
        $venue = Venue::factory()->make([
            'name' => 'Fox Hall',
            'street' => '1 Example Road',
            'city' => 'Anytown',
            'region' => 'Kent',
            'postcode' => 'AB1 2CD',
        ]);

        $this->assertSame('Fox Hall (Anytown, AB1 2CD)', $venue->optionLabel());

        $venue->city = null;
        $this->assertSame('Fox Hall (Kent, AB1 2CD)', $venue->optionLabel());

        $venue->region = null;
        $this->assertSame('Fox Hall (AB1 2CD)', $venue->optionLabel());

        $venue->postcode = null;
        $this->assertSame('Fox Hall (1 Example Road)', $venue->optionLabel());

        $venue->street = null;
        $this->assertSame('Fox Hall', $venue->optionLabel());
    }

    public function test_editing_a_catalog_venue_does_not_change_booking_sequence(): void
    {
        $venue = Venue::factory()->create(['name' => 'Original Hall']);
        $booking = Booking::factory()->create([
            'venue_id' => $venue->getKey(),
        ]);
        $sequence = $booking->sequence;

        $venue->name = 'Renamed Hall';
        $venue->save();

        $this->assertSame($sequence, $booking->fresh()->sequence);
    }

    public function test_admin_create_persists_postal_parts_as_a_country_code(): void
    {
        $admin = User::factory()->systemAdmin()->create();

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(CreateVenue::class)
            ->fillForm([
                'name' => 'Postal Hall',
                'street' => 'Newbold Road',
                'street2' => 'Rear of hall',
                'city' => 'Kirkby Mallory',
                'region' => 'Leicestershire',
                'postcode' => 'LE9 7QG',
                'country' => 'GB',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $venue = Venue::query()->where('name', 'Postal Hall')->first();

        $this->assertNotNull($venue);
        $this->assertSame('Newbold Road', $venue->street);
        $this->assertSame('Rear of hall', $venue->street2);
        $this->assertSame('Kirkby Mallory', $venue->city);
        $this->assertSame('Leicestershire', $venue->region);
        $this->assertSame('LE9 7QG', $venue->postcode);
        $this->assertSame('GB', $venue->country);
        $this->assertNull($venue->coords);
    }

    public function test_admin_can_create_a_venue_with_coordinates(): void
    {
        $admin = User::factory()->systemAdmin()->create();

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(CreateVenue::class)
            ->assertFormFieldHidden('map')
            ->fillForm([
                'name' => 'Located Hall',
            ])
            ->set('data.has_map_pin', true)
            ->assertFormFieldVisible('map')
            ->set('data.latitude', 51.5074)
            ->set('data.longitude', -0.1278)
            ->call('create')
            ->assertHasNoFormErrors();

        $venue = Venue::query()->where('name', 'Located Hall')->first();

        $this->assertNotNull($venue);
        $this->assertEqualsWithDelta(51.5074, (float) $venue->latitude, 0.0001);
        $this->assertEqualsWithDelta(-0.1278, (float) $venue->longitude, 0.0001);
        $this->assertNotNull($venue->coords);
    }

    public function test_admin_create_map_pin_toggle_stores_the_default_coordinates(): void
    {
        $admin = User::factory()->systemAdmin()->create();

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(CreateVenue::class)
            ->fillForm([
                'name' => 'Default Pin Hall',
            ])
            ->set('data.has_map_pin', true)
            ->call('create')
            ->assertHasNoFormErrors();

        $venue = Venue::query()->where('name', 'Default Pin Hall')->first();

        $this->assertNotNull($venue);
        $this->assertEqualsWithDelta(Coords::DEFAULT_MAP_LATITUDE, (float) $venue->latitude, 0.0001);
        $this->assertEqualsWithDelta(Coords::DEFAULT_MAP_LONGITUDE, (float) $venue->longitude, 0.0001);
    }

    public function test_admin_can_remove_venue_coordinates(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $venue = Venue::factory()->located()->create([
            'name' => 'Pinned Hall',
        ]);

        Filament::setCurrentPanel('admin');
        $this->actingAs($admin);

        Livewire::test(EditVenue::class, ['record' => $venue->getKey()])
            ->assertFormFieldVisible('map')
            ->set('data.has_map_pin', false)
            ->assertFormFieldHidden('map')
            ->call('save')
            ->assertHasNoFormErrors();

        $venue->refresh();

        $this->assertNull($venue->latitude);
        $this->assertNull($venue->longitude);
        $this->assertNull($venue->coords);
    }

    public function test_booking_view_map_action_opens_when_the_venue_has_a_pin(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->teams()->first();
        $member = User::factory()->teamMember($team)->create();
        $venue = Venue::factory()->located()->create([
            'name' => 'Pinned Hall',
        ]);
        $booking = Booking::factory()->confirmed()->create([
            'venue_id' => $venue->getKey(),
        ]);

        Filament::setCurrentPanel('manage');
        $this->actingAs($manager);

        Livewire::test(ManageViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('Pinned Hall')
            ->assertSee('View Map')
            ->assertInfolistActionVisible('location', 'map')
            ->mountInfolistAction('location', 'map')
            ->assertMountedActionModalSee('Pinned Hall');

        Filament::setCurrentPanel('teams');
        $this->actingAs($member);

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('View Map')
            ->assertInfolistActionVisible('location', 'map')
            ->mountInfolistAction('location', 'map')
            ->assertMountedActionModalSee('Pinned Hall');
    }

    public function test_booking_view_map_action_is_hidden_when_the_venue_has_no_pin(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->teams()->first();
        $member = User::factory()->teamMember($team)->create();
        $venue = Venue::factory()->create([
            'name' => 'Unpinned Hall',
        ]);
        $booking = Booking::factory()->confirmed()->create([
            'venue_id' => $venue->getKey(),
        ]);

        Filament::setCurrentPanel('manage');
        $this->actingAs($manager);

        Livewire::test(ManageViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('Unpinned Hall')
            ->assertDontSee('View Map')
            ->assertInfolistActionDoesNotExist('location', 'map');

        Filament::setCurrentPanel('teams');
        $this->actingAs($member);

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertDontSee('View Map')
            ->assertInfolistActionDoesNotExist('location', 'map');
    }
}
