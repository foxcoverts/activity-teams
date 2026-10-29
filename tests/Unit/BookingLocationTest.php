<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Venue;
use App\Support\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingLocationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * When a Booking has a Venue, the venue's attributes are accessible through location* helpers.
     */
    public function test_booking_location_fields_from_venue(): void
    {
        $venue = Venue::factory()->create([
            'name' => 'Test Venue',
            'street' => '123 Test Street',
            'country' => 'GB',
            'latitude' => 51.5074,
            'longitude' => -0.1278,
        ]);
        $booking = Booking::factory()->create([
            'venue_id' => $venue->id,
        ]);

        $this->assertEquals(new Location(
            name: 'Test Venue',
            street: '123 Test Street',
            street2: null,
            city: null,
            region: null,
            postcode: null,
            country: 'GB',
        ), $booking->location);
        $this->assertSame([
            'street' => '123 Test Street',
            'country_name' => 'United Kingdom',
        ], $booking->location->addressArray());
        $this->assertNotNull($booking->coords);
        $this->assertEqualsWithDelta(51.5074, $booking->coords->latitude, 0.0001);
        $this->assertEqualsWithDelta(-0.1278, $booking->coords->longitude, 0.0001);
    }

    /**
     * When a Booking has a Venue, and the venue's location fields are null, then null is given.
     */
    public function test_booking_location_fields_from_venue_are_nullable(): void
    {
        $venue = Venue::factory()->create([
            'name' => 'Test Venue',
            'country' => null,
            'latitude' => null,
            'longitude' => null,
        ]);
        $booking = Booking::factory()->create([
            'venue_id' => $venue->id,
        ]);

        $this->assertEquals('Test Venue', $booking->location->name);
        $this->assertSame([], $booking->location->addressArray());
        $this->assertNull($booking->coords);
    }
}
