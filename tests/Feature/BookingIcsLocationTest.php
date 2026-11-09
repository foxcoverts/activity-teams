<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeStatus;
use App\Enums\CalendarMethod;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Support\BookingIcsFactory;
use App\Support\IcsValidator;
use App\Support\InboundMailAddress;
use DateInterval;
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

        $this->assertValidIcs($ics);
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

        $this->assertValidIcs($ics);
        $this->assertStringContainsString('METHOD:REQUEST', $ics);
        $this->assertStringContainsString('ORGANIZER', $ics);
        $this->assertStringContainsString(InboundMailAddress::forBooking($booking), $ics);
        $this->assertStringContainsString('SEQUENCE:'.(int) $booking->sequence, $ics);
        $this->assertStringContainsString('ATTENDEE', $ics);
        $this->assertStringContainsString('ROLE=REQ-PARTICIPANT', $ics);
        $this->assertStringContainsString('CUTYPE=INDIVIDUAL', $ics);
        $this->assertStringContainsString('PARTSTAT=ACCEPTED', $ics);
    }

    public function test_ics_uses_named_timezone_and_vtimezone(): void
    {
        $booking = Booking::factory()->confirmed()->create([
            'timezone' => 'Europe/London',
            'start_at' => '2026-11-14 09:30:00',
            'end_at' => '2026-11-14 11:30:00',
        ]);

        $ics = str_replace("\r\n ", '', app(BookingIcsFactory::class)->make([$booking], 'Test'));

        $this->assertValidIcs($ics);
        $this->assertStringContainsString('DTSTART;TZID=Europe/London:20261114T093000', $ics);
        $this->assertStringContainsString('DTEND;TZID=Europe/London:20261114T113000', $ics);
        $this->assertStringContainsString('BEGIN:VTIMEZONE', $ics);
        $this->assertStringContainsString('TZID:Europe/London', $ics);
        $this->assertStringContainsString('X-WR-TIMEZONE:Europe/London', $ics);
        $this->assertStringNotContainsString('TZID=+00', $ics);
        $this->assertStringNotContainsString('TZID=-', $ics);
    }

    public function test_ics_emits_utc_with_z_suffix(): void
    {
        $booking = Booking::factory()->confirmed()->create([
            'timezone' => 'UTC',
            'start_at' => '2026-11-14 09:30:00',
            'end_at' => '2026-11-14 11:30:00',
        ]);

        $ics = str_replace("\r\n ", '', app(BookingIcsFactory::class)->make([$booking], 'Test'));

        $this->assertValidIcs($ics);
        $this->assertStringContainsString('DTSTART:20261114T093000Z', $ics);
        $this->assertStringContainsString('DTEND:20261114T113000Z', $ics);
        $this->assertStringNotContainsString('BEGIN:VTIMEZONE', $ics);
        $this->assertStringNotContainsString('TZID=', $ics);
    }

    public function test_ics_includes_url_last_modified_transparency_and_description(): void
    {
        $booking = Booking::factory()->confirmed()->create([
            'group_name' => '1st Example Scouts',
            'notes' => 'Bring waterproofs.',
            'timezone' => 'Europe/London',
            'start_at' => '2026-11-14 09:30:00',
            'end_at' => '2026-11-14 11:30:00',
        ]);

        $ics = str_replace("\r\n ", '', app(BookingIcsFactory::class)->make([$booking], 'Test'));

        $this->assertValidIcs($ics);
        $this->assertStringContainsString('URL:'.route('booking.preview', $booking), $ics);
        $this->assertStringContainsString('LAST-MODIFIED:', $ics);
        $this->assertStringContainsString('TRANSP:OPAQUE', $ics);
        $this->assertStringContainsString('CLASS:PRIVATE', $ics);
        $this->assertStringContainsString('1st Example Scouts', $ics);
        $this->assertStringContainsString('Bring waterproofs.', $ics);
        $this->assertStringContainsString('X-ALT-DESC;FMTTYPE=text/html:', $ics);
    }

    public function test_mail_ics_marks_needs_action_and_lead_comment(): void
    {
        $lead = User::factory()->teamMember()->create();
        $booking = Booking::factory()->confirmed()->create([
            'team_id' => $lead->teams()->first()->getKey(),
            'lead_instructor_id' => $lead->getKey(),
            'lead_instructor_notes' => 'Meet at the gate.',
        ]);
        $booking->attendees()->attach($lead->getKey(), [
            'status' => BookingAttendeeStatus::NeedsAction->value,
            'token' => 'token',
        ]);
        $booking->unsetRelation('attendees');

        $ics = str_replace("\r\n ", '', app(BookingIcsFactory::class)->make(
            [$booking->fresh(['activity', 'venue', 'attendees', 'related'])],
            'Invite',
            method: CalendarMethod::Request,
            forAttendee: $lead,
            rsvp: true,
        ));

        $this->assertValidIcs($ics);
        $this->assertStringContainsString('PARTSTAT=NEEDS-ACTION', $ics);
        $this->assertStringContainsString('ROLE=CHAIR', $ics);
        $this->assertStringContainsString('COMMENT:Meet at the gate.', $ics);
    }

    public function test_mail_ics_marks_the_lead_instructor_as_chair(): void
    {
        $lead = User::factory()->teamMember()->create();
        $booking = Booking::factory()->confirmed()->create([
            'team_id' => $lead->teams()->first()->getKey(),
            'lead_instructor_id' => $lead->getKey(),
        ]);
        $booking->attendees()->attach($lead->getKey(), [
            'status' => 'accepted',
            'token' => 'token',
        ]);
        $booking->unsetRelation('attendees');

        $ics = app(BookingIcsFactory::class)->make(
            [$booking->fresh(['activity', 'venue', 'attendees', 'related'])],
            'Invite',
            method: CalendarMethod::Request,
            forAttendee: $lead,
            rsvp: true,
        );

        $this->assertValidIcs($ics);
        $this->assertStringContainsString('ROLE=CHAIR', $ics);
        $this->assertStringNotContainsString('ROLE=REQ-PARTICIPANT', $ics);
    }

    public function test_feed_ics_includes_published_ttl(): void
    {
        $booking = Booking::factory()->confirmed()->create([
            'timezone' => 'Europe/London',
        ]);

        $ics = app(BookingIcsFactory::class)->make(
            [$booking],
            'Feed',
            publishedTtl: DateInterval::createFromDateString('7 days') ?: null,
        );

        $this->assertValidIcs($ics);
        $this->assertStringContainsString('X-PUBLISHED-TTL:P7D', $ics);
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

        $this->assertValidIcs($ics);
        $this->assertStringContainsString('LOCATION:Somewhere', $ics);
        $this->assertStringNotContainsString('GEO:', $ics);
        $this->assertStringNotContainsString('ALTREP=', $ics);
        $this->assertStringNotContainsString('BEGIN:VLOCATION', $ics);
    }

    private function assertValidIcs(string $ics): void
    {
        $errors = IcsValidator::invalidMessages($ics);

        $this->assertSame([], $errors, implode("\n", $errors));
    }
}
