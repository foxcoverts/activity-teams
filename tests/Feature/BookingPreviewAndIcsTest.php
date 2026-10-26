<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeStatus;
use App\Filament\Member\Pages\CalendarLinks;
use App\Filament\Member\Resources\Bookings\BookingResource;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingPreviewAndIcsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_preview_is_available_without_login(): void
    {
        $booking = $this->sharedBooking();

        $this->get(route('booking.preview', $booking))
            ->assertOk()
            ->assertSee('Climbing on Sat, Oct 17, 2026')
            ->assertSee('Climbing')
            ->assertSee('Fox Coverts Campsite')
            ->assertSee('Sat 17 Oct 2026')
            ->assertSee('09:00 - 11:00')
            ->assertSee(__('Can you attend this event?'))
            ->assertSee(__('View full details'))
            ->assertSee(__('Please log in to view full details and to RSVP.'))
            ->assertSee('images/dates/10/10-17.png', false)
            ->assertSee('Climbing from 09:00 to 11:00 at Fox Coverts Campsite.', false)
            ->assertDontSee('Secret Group Name')
            ->assertDontSee('Secret notes about the group')
            ->assertDontSee('Lead Instructor Person')
            ->assertDontSee('Attendee Person')
            ->assertDontSee('tentative')
            ->assertSessionHas('url.intended', BookingResource::getUrl('view', ['record' => $booking], panel: 'member'));
    }

    public function test_booking_url_shows_public_summary_to_guests(): void
    {
        $booking = $this->sharedBooking();

        $this->get(BookingResource::getUrl('view', ['record' => $booking], panel: 'member'))
            ->assertOk()
            ->assertSee('Climbing on Sat, Oct 17, 2026')
            ->assertSee(__('Can you attend this event?'))
            ->assertSee(__('View full details'))
            ->assertSee('<meta property="og:title" content="Climbing on Sat, Oct 17, 2026">', false)
            ->assertSee('<meta property="og:image" content="', false)
            ->assertSee('images/dates/10/10-17.png', false)
            ->assertSee('<meta property="og:image:width" content="700">', false)
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertDontSee('Secret Group Name')
            ->assertDontSee(__('Lead instructor'))
            ->assertSessionHas('url.intended', BookingResource::getUrl('view', ['record' => $booking], panel: 'member'));

        $this->get(BookingResource::getUrl('index', panel: 'member'))
            ->assertRedirect('/login');
    }

    public function test_overnight_preview_uses_the_local_start_day(): void
    {
        $booking = Booking::factory()->create([
            'activity_id' => Activity::factory()->create(['name' => 'Climbing']),
            'venue_id' => Venue::factory()->create(['name' => 'Fox Coverts Campsite']),
            'group_name' => 'Secret Group Name',
            'timezone' => 'Europe/London',
            'start_at' => '2026-10-24 21:00:00',
            'end_at' => '2026-10-24 23:30:00',
        ]);

        $this->get(route('booking.preview', $booking))
            ->assertOk()
            ->assertSee('Climbing on Sat, Oct 24, 2026')
            ->assertSee('Climbing from Sat 24 Oct 2026 22:00 to Sun 25 Oct 2026 00:30 at Fox Coverts Campsite.', false)
            ->assertSee('images/dates/10/10-24.png', false)
            ->assertDontSee('Secret Group Name');
    }

    public function test_signed_in_share_link_opens_the_booking(): void
    {
        $user = User::factory()->teamMember()->create();
        $booking = $this->sharedBooking();

        $this->actingAs($user)
            ->get(route('booking.preview', $booking))
            ->assertOk()
            ->assertSee(__('View full details'))
            ->assertSee(BookingResource::getUrl('view', ['record' => $booking], panel: 'member'), false)
            ->assertDontSee(__('Please log in to view full details and to RSVP.'))
            ->assertDontSee('Secret Group Name');
    }

    public function test_booking_url_stays_the_full_view_for_members(): void
    {
        $user = User::factory()->teamMember()->create();
        $booking = Booking::factory()->confirmed()->create([
            'group_name' => 'Member Booking',
        ]);

        $this->actingAs($user)
            ->get(BookingResource::getUrl('view', ['record' => $booking], panel: 'member'))
            ->assertOk()
            ->assertSee('Member Booking')
            ->assertDontSee(__('Lead instructor'))
            ->assertDontSee(__('Can you attend this event?'));
    }

    public function test_ics_feed_requires_token_and_returns_calendar(): void
    {
        $user = User::factory()->teamMember()->create([
            'ical_token' => User::generateToken(),
        ]);
        $booking = Booking::factory()->confirmed()->create();
        $booking->attendees()->attach($user->id, [
            'status' => BookingAttendeeStatus::Accepted->value,
        ]);

        $this->get(route('booking.ics', $user))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertSee('BEGIN:VCALENDAR', false)
            ->assertSee('CLASS:PRIVATE', false);
    }

    public function test_ics_feed_marks_linked_bookings_as_siblings(): void
    {
        $user = User::factory()->teamMember()->create([
            'ical_token' => User::generateToken(),
        ]);
        $saturday = Booking::factory()->confirmed()->create([
            'start_at' => '2026-12-12 09:00:00',
            'end_at' => '2026-12-12 11:00:00',
            'timezone' => 'Europe/London',
        ]);
        $sunday = Booking::factory()->confirmed()->create([
            'team_id' => $saturday->team_id,
            'start_at' => '2026-12-13 09:00:00',
            'end_at' => '2026-12-13 11:00:00',
            'timezone' => 'Europe/London',
        ]);
        $saturday->attendees()->attach($user->id, [
            'status' => BookingAttendeeStatus::Accepted->value,
        ]);
        $saturday->relate($sunday);

        $ics = str_replace("\r\n ", '', $this->get(route('booking.ics', $user))
            ->assertOk()
            ->assertSee('CLASS:PRIVATE', false)
            ->getContent());

        $this->assertStringContainsString('RELATED-TO;RELTYPE=SIBLING:'.$sunday->uid, $ics);
        $this->assertStringContainsString('UID:'.$saturday->uid, $ics);
    }

    public function test_regenerating_calendar_links_stops_the_old_token(): void
    {
        $user = User::factory()->teamMember()->create([
            'ical_token' => User::generateToken(),
        ]);
        $oldUrl = route('booking.ics', $user);

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($user);

        $this->get($oldUrl)->assertOk();

        Livewire::test(CalendarLinks::class)
            ->callAction('regenerate');

        $user->refresh();

        $this->get($oldUrl)->assertNotFound();
        $this->get(route('booking.ics', $user))->assertOk();
        $this->assertNotSame($oldUrl, route('booking.ics', $user));
    }

    private function sharedBooking(): Booking
    {
        $lead = User::factory()->create(['name' => 'Lead Instructor Person']);
        $attendee = User::factory()->create(['name' => 'Attendee Person']);
        $booking = Booking::factory()->create([
            'activity_id' => Activity::factory()->create(['name' => 'Climbing']),
            'venue_id' => Venue::factory()->create(['name' => 'Fox Coverts Campsite']),
            'group_name' => 'Secret Group Name',
            'notes' => 'Secret notes about the group',
            'timezone' => 'Europe/London',
            'start_at' => '2026-10-17 08:00:00',
            'end_at' => '2026-10-17 10:00:00',
            'lead_instructor_id' => $lead->id,
        ]);
        $booking->attendees()->attach($attendee->id, [
            'status' => BookingAttendeeStatus::Accepted->value,
        ]);

        return $booking;
    }
}
