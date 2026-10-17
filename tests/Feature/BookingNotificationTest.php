<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use App\Events\BookingCancelled;
use App\Events\BookingChanged;
use App\Events\BookingConfirmed;
use App\Events\BookingInvite;
use App\Events\BookingRestored;
use App\Jobs\SendBookingNotification;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingChangedMail;
use App\Mail\BookingConfirmedMail;
use App\Mail\BookingInviteMail;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BookingNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_mail_puts_the_subject_on_the_envelope(): void
    {
        [$booking, $attendee] = $this->bookingWithAttendee(BookingAttendeeStatus::Accepted);

        $updated = new BookingChangedMail($booking, $attendee);
        $confirmed = new BookingConfirmedMail($booking, $attendee);
        $cancelled = new BookingCancelledMail($booking, $attendee, 'Weather');

        $updated->assertHasSubject('Booking updated');
        $confirmed->assertHasSubject('Booking confirmed');
        $cancelled->assertHasSubject('Booking cancelled');

        $html = $updated->render();
        $respondUrl = route('respond', [$booking, $attendee, 'invite' => $booking->attendees()->find($attendee)->attendance->token]);

        $this->assertStringContainsString('You said you can help.', $html);
        $this->assertStringContainsString('#f0f9ff', $html);
        $this->assertStringContainsString('View response', $html);
        $this->assertStringContainsString($respondUrl, $html);
        $this->assertStringNotContainsString('Change response', $html);
        $this->assertStringNotContainsString('/bookings/'.$booking->id, $html);
        $this->assertStringNotContainsString('Respond', $html);
        $this->assertStringContainsString('Weather', $cancelled->render());
    }

    public function test_unanswered_update_uses_the_respond_button(): void
    {
        [$booking, $attendee] = $this->bookingWithAttendee();

        $html = (new BookingChangedMail($booking, $attendee))->render();

        $this->assertStringContainsString('Respond', $html);
        $this->assertStringContainsString(
            route('respond', [$booking, $attendee, 'invite' => $booking->attendees()->find($attendee)->attendance->token]),
            $html,
        );
        $this->assertStringNotContainsString('You have not responded', $html);
        $this->assertStringNotContainsString('You said', $html);
        $this->assertStringNotContainsString('#f0f9ff', $html);
        $this->assertStringNotContainsString('View response', $html);
        $this->assertStringNotContainsString('/bookings/'.$booking->id, $html);
    }

    public function test_past_booking_keeps_the_view_link_when_they_have_not_answered(): void
    {
        [$booking, $attendee] = $this->bookingWithAttendee(BookingAttendeeStatus::NeedsAction, [
            'start_at' => now()->subHours(3),
            'end_at' => now()->subHour(),
        ]);

        $html = (new BookingChangedMail($booking, $attendee))->render();

        $this->assertStringContainsString('View response', $html);
        $this->assertStringContainsString(
            route('respond', [$booking, $attendee, 'invite' => $booking->attendees()->find($attendee)->attendance->token]),
            $html,
        );
        $this->assertStringNotContainsString('#f0f9ff', $html);
        $this->assertStringNotContainsString('Respond', $html);
        $this->assertStringNotContainsString('Change response', $html);
        $this->assertStringNotContainsString('/bookings/'.$booking->id, $html);
    }

    public function test_an_update_followed_by_confirmation_sends_one_email(): void
    {
        [$booking, $attendee, $actor] = $this->bookingWithAttendee();

        Queue::fake();
        Mail::fake();

        event(new BookingChanged($booking, $actor));
        $booking->status = BookingStatus::Confirmed;
        $booking->save();
        event(new BookingConfirmed($booking->fresh(), $actor));

        $jobs = Queue::pushed(SendBookingNotification::class);
        $this->assertCount(2, $jobs);

        foreach ($jobs as $job) {
            $job->handle();
        }

        Mail::assertSent(BookingConfirmedMail::class, 1);
        Mail::assertNotSent(BookingChangedMail::class);
    }

    public function test_restoring_a_booking_sends_one_update_instead_of_an_invite(): void
    {
        [$booking, $attendee, $actor] = $this->bookingWithAttendee(BookingAttendeeStatus::NeedsAction, [
            'status' => BookingStatus::Tentative,
        ]);

        Queue::fake();
        Mail::fake();

        event(new BookingInvite($booking, $attendee));
        event(new BookingRestored($booking, $actor));

        foreach (Queue::pushed(SendBookingNotification::class) as $job) {
            $job->handle();
        }

        Mail::assertSent(BookingChangedMail::class, function (BookingChangedMail $mail): bool {
            return $mail->envelope()->subject === 'Booking updated';
        });
        Mail::assertNotSent(BookingInviteMail::class);
    }

    public function test_cancellation_replaces_a_pending_update(): void
    {
        [$booking, $attendee, $actor] = $this->bookingWithAttendee();

        Queue::fake();
        Mail::fake();

        event(new BookingChanged($booking, $actor));
        $booking->status = BookingStatus::Cancelled;
        $booking->save();
        event(new BookingCancelled($booking->fresh(), $actor, 'Weather'));

        foreach (Queue::pushed(SendBookingNotification::class) as $job) {
            $job->handle();
        }

        Mail::assertSent(BookingCancelledMail::class, function (BookingCancelledMail $mail) use ($attendee): bool {
            return $mail->hasTo($attendee->email)
                && str_contains($mail->render(), 'Weather')
                && ! str_contains($mail->render(), 'Change response');
        });
        Mail::assertNotSent(BookingChangedMail::class);
    }

    public function test_an_invite_on_its_own_is_still_an_invite(): void
    {
        [$booking, $attendee] = $this->bookingWithAttendee();

        Queue::fake();
        Mail::fake();

        event(new BookingInvite($booking, $attendee));

        $jobs = Queue::pushed(SendBookingNotification::class);
        $this->assertCount(1, $jobs);
        $jobs->first()->handle();

        Mail::assertSent(BookingInviteMail::class, 1);
        Mail::assertNotSent(BookingChangedMail::class);
    }

    /**
     * @param  array<string, mixed>  $bookingAttributes
     * @return array{0: Booking, 1: User, 2: User}
     */
    private function bookingWithAttendee(BookingAttendeeStatus $status = BookingAttendeeStatus::NeedsAction, array $bookingAttributes = []): array
    {
        $actor = User::factory()->teamLeader()->create();
        $attendee = User::factory()->teamMember()->create();
        $booking = Booking::factory()->create($bookingAttributes);
        $booking->attendees()->attach($attendee->id, [
            'status' => $status->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        return [$booking, $attendee, $actor];
    }
}
