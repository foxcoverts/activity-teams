<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Venues\Pages\CreateVenue;
use App\Filament\Admin\Resources\Venues\Pages\EditVenue;
use App\Filament\Admin\Resources\Venues\VenueResource as AdminVenueResource;
use App\Filament\Manage\Resources\Bookings\BookingResource;
use App\Filament\Manage\Resources\Bookings\Pages\ViewBooking as ManageViewBooking;
use App\Filament\Manage\Resources\Venues\VenueResource as ManageVenueResource;
use App\Filament\Member\Resources\Bookings\Pages\ViewBooking as MemberViewBooking;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Support\Coords;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_manage_venue_shows_address(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->teams()->first();
        $venue = Venue::factory()->create([
            'name' => 'Sample Venue',
            'street' => '123 Main St',
            'city' => 'Sample City',
            'region' => 'Sample Region',
            'postcode' => '12345',
            'country' => 'GB',
        ]);

        $this->actingAs($manager);
        Filament::setTenant($team);
        Filament::setCurrentPanel('manage');

        $this->get(ManageVenueResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Sample Venue');

        $this->get(ManageVenueResource::getUrl('view', ['record' => $venue->getKey()]))
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
        $team = $manager->teams()->first();
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

        $this->actingAs($manager);
        Filament::setTenant($team);
        Filament::setCurrentPanel('manage');

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

        $this->actingAs($manager);
        Filament::setTenant($team);
        Filament::setCurrentPanel(Filament::getPanel('manage'));

        Livewire::test(ManageViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('Pinned Hall')
            ->assertSee('View Map')
            ->assertInfolistActionVisible('location', 'map')
            ->mountInfolistAction('location', 'map')
            ->assertMountedActionModalSee('Pinned Hall');

        $this->actingAs($member);
        Filament::setCurrentPanel(Filament::getPanel('member'));

        Livewire::test(MemberViewBooking::class, ['record' => $booking->getRouteKey()])
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

        $this->actingAs($manager);
        Filament::setTenant($team);
        Filament::setCurrentPanel(Filament::getPanel('manage'));

        Livewire::test(ManageViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('Unpinned Hall')
            ->assertDontSee('View Map')
            ->assertInfolistActionDoesNotExist('location', 'map');

        $this->actingAs($member);
        Filament::setCurrentPanel(Filament::getPanel('member'));

        Livewire::test(MemberViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertDontSee('View Map')
            ->assertInfolistActionDoesNotExist('location', 'map');
    }
}
