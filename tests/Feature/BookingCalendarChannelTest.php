<?php

namespace Tests\Feature;

use App\Actions\ResendBookingIcsAction;
use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use App\Enums\CalendarMethod;
use App\Enums\MailChannel;
use App\Enums\MailGroup;
use App\Enums\MembershipRole;
use App\Events\BookingCancelled;
use App\Events\BookingChanged;
use App\Events\BookingInvite;
use App\Filament\Teams\Resources\Bookings\Pages\ViewBooking as TeamsViewBooking;
use App\Filament\Teams\Resources\Bookings\RelationManagers\AttendeesRelationManager;
use App\Jobs\SendBookingNotification;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingChangedMail;
use App\Mail\BookingIcsRefreshMail;
use App\Mail\BookingInviteMail;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use App\Notifications\BookingHeldNotification;
use App\Support\InboundMailAddress;
use App\Support\MailPreference;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class BookingCalendarChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_outbound_invite_does_not_open_the_calendar_channel(): void
    {
        [$booking, $attendee] = $this->bookingWithAttendee();

        Queue::fake();
        Mail::fake();

        event(new BookingInvite($booking, $attendee->fresh()));

        foreach (Queue::pushed(SendBookingNotification::class) as $job) {
            $job->handle();
        }

        Mail::assertSent(BookingInviteMail::class);
        $this->assertFalse($booking->fresh()->attendeeHasCalendarChannel($attendee));
    }

    public function test_force_send_delivers_ics_with_prefs_off_without_opening_the_channel(): void
    {
        [$booking, $attendee] = $this->bookingWithAttendee(BookingAttendeeStatus::Accepted);

        MailPreference::save($attendee, MailGroup::Bookings, [
            MailChannel::BookingsMail->value => '0',
        ], $booking);

        Mail::fake();

        app(ResendBookingIcsAction::class)($booking, $attendee, force: true);

        Mail::assertQueued(BookingIcsRefreshMail::class);
        $this->assertFalse($attendee->notifications()->where('type', BookingHeldNotification::class)->exists());
        $this->assertFalse($booking->fresh()->attendeeHasCalendarChannel($attendee));
    }

    public function test_calendar_refresh_mail_looks_like_a_booking_update_with_details(): void
    {
        [$booking, $attendee] = $this->bookingWithAttendee(BookingAttendeeStatus::Accepted);
        MailPreference::tokenFor($attendee);

        $mail = new BookingIcsRefreshMail($booking, $attendee, CalendarMethod::Request);

        $mail->assertSeeInHtml('Booking details');
        $mail->assertSeeInHtml('Here are the current details for this booking.');
        $mail->assertSeeInHtml($booking->activityTitle());
        $mail->assertSeeInHtml($booking->venue->name);
        $mail->assertSeeInHtml(route('mail-preferences.booking', ['user' => $attendee, 'booking' => $booking]));
        $mail->assertSeeInHtml('Manage notifications for this booking');
        $mail->assertDontSeeInHtml('Changes');

        $html = $mail->render();
        $this->assertDoesNotMatchRegularExpression(
            '/Venue:.*?<\/p>\s*<p>\s*<strong>Date:/s',
            $html,
            'Venue and Date should not be separated by a blank paragraph when Group is empty.',
        );
    }

    public function test_inbound_reply_opens_the_channel_and_prefs_off_still_sends_updates(): void
    {
        [$booking, $attendee, $actor] = $this->bookingWithAttendee();

        Mail::fake();

        $this->postCalendar($booking, $attendee, 'REPLY', 'ACCEPTED', (int) $booking->sequence)
            ->assertOk();

        $this->assertTrue($booking->fresh()->attendeeHasCalendarChannel($attendee));

        MailPreference::save($attendee->fresh(), MailGroup::Bookings, [
            MailChannel::BookingsMail->value => '0',
        ], $booking);

        Queue::fake();
        Mail::fake();

        event(new BookingChanged($booking->fresh(), $actor));

        foreach (Queue::pushed(SendBookingNotification::class) as $job) {
            $job->handle();
        }

        Mail::assertSent(BookingChangedMail::class, fn (BookingChangedMail $mail): bool => $mail->hasTo($attendee->email));
        $this->assertFalse($attendee->notifications()->where('type', BookingHeldNotification::class)->exists());
    }

    public function test_inbound_refresh_with_prefs_off_sends_ics_and_opens_the_channel(): void
    {
        [$booking, $attendee] = $this->bookingWithAttendee(BookingAttendeeStatus::Accepted);

        MailPreference::save($attendee, MailGroup::Bookings, [
            MailChannel::BookingsMail->value => '0',
        ], $booking);

        Mail::fake();

        $this->postCalendar($booking, $attendee, 'REFRESH', 'NEEDS-ACTION', (int) $booking->sequence)
            ->assertOk();

        Mail::assertQueued(BookingIcsRefreshMail::class);
        $this->assertTrue($booking->fresh()->attendeeHasCalendarChannel($attendee));
        $this->assertFalse($attendee->notifications()->where('type', BookingHeldNotification::class)->exists());
    }

    public function test_inbound_declined_reply_leaves_the_channel_set(): void
    {
        [$booking, $attendee] = $this->bookingWithAttendee(BookingAttendeeStatus::Accepted);

        Mail::fake();

        $this->postCalendar($booking, $attendee, 'REPLY', 'DECLINED', (int) $booking->sequence)
            ->assertOk();

        $this->assertTrue($booking->fresh()->attendeeHasCalendarChannel($attendee));
        $this->assertSame(
            BookingAttendeeStatus::Declined,
            $booking->fresh()->attendees()->find($attendee->getKey())?->attendance?->status,
        );
    }

    public function test_outbound_cancel_clears_the_calendar_channel(): void
    {
        [$booking, $attendee, $actor] = $this->bookingWithAttendee(BookingAttendeeStatus::Accepted);

        $booking->markCalendarChannel($attendee);
        $this->assertTrue($booking->fresh()->attendeeHasCalendarChannel($attendee));

        MailPreference::save($attendee->fresh(), MailGroup::Bookings, [
            MailChannel::BookingsMail->value => '0',
        ], $booking);

        Queue::fake();
        Mail::fake();

        $booking->refresh();
        $booking->status = BookingStatus::Cancelled;
        $booking->cancellationReason = 'Bad weather';
        $booking->save();
        event(new BookingCancelled($booking->fresh(), $actor, 'Bad weather'));

        foreach (Queue::pushed(SendBookingNotification::class) as $job) {
            $job->handle();
        }

        Mail::assertSent(BookingCancelledMail::class, fn (BookingCancelledMail $mail): bool => $mail->hasTo($attendee->email));
        $this->assertFalse($booking->fresh()->attendeeHasCalendarChannel($attendee));
    }

    public function test_guest_list_calendar_actions_for_needs_action_and_send_to_email(): void
    {
        Filament::setCurrentPanel('teams');

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->create();
        $team->grant($member, MembershipRole::Member);
        $booking = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'lead_instructor_id' => $manager->getKey(),
        ]);
        $booking->attendees()->attach($member->getKey(), [
            'status' => BookingAttendeeStatus::NeedsAction->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        Mail::fake();
        $this->actingAs($member);

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => TeamsViewBooking::class,
        ])
            ->assertTableActionVisible('sendToEmail')
            ->assertTableActionVisible('addToCalendar')
            ->callTableAction('sendToEmail')
            ->assertNotified('Booking details have been sent to your email address.');

        Mail::assertQueued(BookingIcsRefreshMail::class);
        $this->assertFalse($booking->fresh()->attendeeHasCalendarChannel($member));
        $this->assertSame(
            BookingAttendeeStatus::NeedsAction,
            $booking->fresh()->attendees()->find($member->getKey())?->attendance?->status,
        );
    }

    public function test_guest_list_hides_calendar_actions_when_declined(): void
    {
        Filament::setCurrentPanel('teams');

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->create();
        $team->grant($member, MembershipRole::Member);
        $booking = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'lead_instructor_id' => $manager->getKey(),
        ]);
        $booking->attendees()->attach($member->getKey(), [
            'status' => BookingAttendeeStatus::Declined->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        $this->actingAs($member);

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => TeamsViewBooking::class,
        ])
            ->assertTableActionHidden('sendToEmail')
            ->assertTableActionHidden('addToCalendar');
    }

    public function test_guest_list_people_group_still_exposes_invite_and_add_for_managers(): void
    {
        Filament::setCurrentPanel('teams');

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $booking = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'lead_instructor_id' => $manager->getKey(),
        ]);
        $booking->attendees()->attach($manager->getKey(), [
            'status' => BookingAttendeeStatus::Accepted->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        $this->actingAs($manager);

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => TeamsViewBooking::class,
        ])
            ->assertTableActionVisible('invite')
            ->assertTableActionVisible('add')
            ->assertTableActionVisible('sendToEmail');
    }

    /**
     * @return array{0: Booking, 1: User, 2: User}
     */
    private function bookingWithAttendee(BookingAttendeeStatus $status = BookingAttendeeStatus::NeedsAction): array
    {
        $actor = User::factory()->teamManager()->create();
        $team = $actor->managedTeams()->firstOrFail();
        $booking = Booking::factory()->create([
            'team_id' => $team->getKey(),
            'lead_instructor_id' => $actor->getKey(),
        ]);
        $attendee = User::factory()->teamMember($team)->create([
            'email' => 'member@example.com',
        ]);
        $booking->attendees()->attach($attendee->getKey(), [
            'status' => $status->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        return [$booking, $attendee, $actor];
    }

    private function postCalendar(
        Booking $booking,
        User $member,
        string $method,
        string $partstat,
        int $sequence,
    ) {
        $uid = InboundMailAddress::forBooking($booking);
        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'METHOD:'.$method,
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'SEQUENCE:'.$sequence,
            'DTSTAMP:'.now()->format('Ymd\THis\Z'),
            'ATTENDEE;PARTSTAT='.$partstat.':mailto:'.$member->email,
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);
        $to = InboundMailAddress::forBooking($booking);

        return $this->postMailWebhook([
            'subject' => 'Calendar: '.$booking->activity->name,
            'date' => now()->toIso8601String(),
            'from' => [
                'text' => $member->email,
                'value' => [['address' => $member->email, 'name' => 'Member']],
            ],
            'to' => [
                'text' => $to,
                'value' => [['address' => $to, 'name' => null]],
            ],
            'attachments' => [[
                'contentType' => 'text/calendar',
                'content' => $ics,
            ]],
            'html' => '<p>reply</p>',
        ]);
    }
}
