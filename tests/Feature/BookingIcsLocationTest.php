<?php

namespace Tests\Feature;

use App\Enums\CalendarMethod;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Support\BookingIcsFactory;
use App\Support\InboundMailAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingIcsLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ics_includes_precise_location_geo_and_microsoft_fields(): void
    {
        $venue = Venue::factory()->create([
            'name' => 'Fox Coverts Campsite',
            'street' => '1 Scout Lane',
            'street2' => 'Field Gate',
            'city' => 'Leicester',
            'region' => 'Leicestershire',
            'postcode' => 'LE1 1AA',
            'country' => 'GB',
            'latitude' => 52.6362,
            'longitude' => -1.1332,
        ]);
        $booking = Booking::factory()->confirmed()->create([
            'venue_id' => $venue->getKey(),
        ]);

        $ics = str_replace("\r\n ", '', app(BookingIcsFactory::class)->make([$booking], 'Test'));

        $this->assertStringContainsString('LOCATION;ALTREP=', $ics);
        $this->assertStringContainsString('1 Scout Lane', $ics);
        $this->assertStringContainsString('Field Gate', $ics);
        $this->assertStringContainsString('Leicester', $ics);
        $this->assertStringContainsString('LE1 1AA', $ics);
        $this->assertStringNotContainsString('United Kingdom', explode('LOCATION', $ics)[1] ?? '');
        $this->assertStringContainsString('GEO:52.636200;-1.133200', $ics);
        $this->assertStringContainsString('X-APPLE-STRUCTURED-LOCATION', $ics);
        $this->assertStringContainsString('X-TITLE=Fox Coverts Campsite', $ics);
        $this->assertStringContainsString('X-MICROSOFT-LOCATIONSTREET:1 Scout Lane', $ics);
        $this->assertStringContainsString('Field Gate', $ics);
        $this->assertStringContainsString('X-MICROSOFT-LOCATIONCITY:Leicester', $ics);
        $this->assertStringContainsString('X-MICROSOFT-LOCATIONCOUNTRY:United Kingdom', $ics);
        $this->assertStringContainsString('BEGIN:VLOCATION', $ics);
        $this->assertStringContainsString('NAME:Fox Coverts Campsite', $ics);
        $this->assertStringNotContainsString('METHOD:', $ics);
    }

    public function test_mail_ics_includes_method_organizer_and_sequence(): void
    {
        $booking = Booking::factory()->confirmed()->create();
        $attendee = User::factory()->teamMember($booking->team)->create();
        $booking->attendees()->attach($attendee->getKey(), [
            'status' => 'accepted',
            'token' => 'token',
        ]);
        $booking->unsetRelation('attendees');

        $ics = app(BookingIcsFactory::class)->make(
            [$booking->fresh(['activity', 'venue', 'attendees', 'related'])],
            'Invite',
            method: CalendarMethod::Request,
            forAttendee: $attendee,
            rsvp: true,
        );

        $this->assertStringContainsString('METHOD:REQUEST', $ics);
        $this->assertStringContainsString('ORGANIZER', $ics);
        $this->assertStringContainsString(InboundMailAddress::forBooking($booking), $ics);
        $this->assertStringContainsString('SEQUENCE:'.(int) $booking->sequence, $ics);
        $this->assertStringContainsString('ATTENDEE', $ics);
    }

    public function test_name_only_location_remains_valid_without_geo(): void
    {
        $venue = Venue::factory()->create([
            'name' => 'Somewhere',
            'street' => null,
            'city' => null,
            'postcode' => null,
            'latitude' => null,
            'longitude' => null,
        ]);
        $booking = Booking::factory()->create(['venue_id' => $venue->getKey()]);

        $ics = app(BookingIcsFactory::class)->make([$booking], 'Test');

        $this->assertStringContainsString('LOCATION:Somewhere', $ics);
        $this->assertStringNotContainsString('GEO:', $ics);
        $this->assertStringNotContainsString('ALTREP=', $ics);
        $this->assertStringNotContainsString('BEGIN:VLOCATION', $ics);
    }
}
