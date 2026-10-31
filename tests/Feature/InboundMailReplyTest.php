<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use App\Enums\InboundMailOutcome;
use App\Events\BookingCancelled;
use App\Jobs\SendBookingNotification;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingIcsRefreshMail;
use App\Mail\UnhandledInboundMail;
use App\Models\Booking;
use App\Models\InboundMail;
use App\Models\User;
use App\Support\InboundMailAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InboundMailReplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_reply_updates_attendance_via_imip(): void
    {
        Mail::fake();

        [$booking, $member] = $this->bookingWithAttendee();

        $this->postCalendar($booking, $member, 'REPLY', 'ACCEPTED', sequence: (int) $booking->sequence)
            ->assertOk();

        $this->assertSame(
            BookingAttendeeStatus::Accepted,
            $booking->fresh()->attendees()->find($member->getKey())?->attendance?->status,
        );

        $activity = $booking->activitiesAsSubject()
            ->where('event', 'attendance_updated')
            ->latest()
            ->first();

        $this->assertSame('imip', $activity?->getProperty('via'));
        $this->assertSame(InboundMailOutcome::Applied, InboundMail::query()->first()?->outcome);
        Mail::assertNothingSent();
    }

    public function test_stale_reply_still_records_attendance_and_resends_request(): void
    {
        Mail::fake();

        [$booking, $member] = $this->bookingWithAttendee();
        $booking->forceFill(['sequence' => 3])->saveQuietly();

        $this->postCalendar($booking, $member, 'REPLY', 'DECLINED', sequence: 1)
            ->assertOk();

        $this->assertSame(
            BookingAttendeeStatus::Declined,
            $booking->fresh()->attendees()->find($member->getKey())?->attendance?->status,
        );

        Mail::assertQueued(BookingIcsRefreshMail::class);
        $this->assertSame(InboundMailOutcome::Resent, InboundMail::query()->first()?->outcome);
    }

    public function test_refresh_resends_current_invite_without_changing_attendance(): void
    {
        Mail::fake();

        [$booking, $member] = $this->bookingWithAttendee(BookingAttendeeStatus::Accepted);

        $this->postCalendar($booking, $member, 'REFRESH', 'NEEDS-ACTION', sequence: (int) $booking->sequence)
            ->assertOk();

        $this->assertSame(
            BookingAttendeeStatus::Accepted,
            $booking->fresh()->attendees()->find($member->getKey())?->attendance?->status,
        );

        Mail::assertQueued(BookingIcsRefreshMail::class);
        $this->assertSame(InboundMailOutcome::Resent, InboundMail::query()->first()?->outcome);
    }

    public function test_unmapped_reply_is_queued_and_alerts_managers(): void
    {
        Mail::fake();

        [$booking] = $this->bookingWithAttendee();
        $manager = User::factory()->teamManager($booking->team)->create();

        $ics = $this->ics($booking, 'stranger@example.com', 'REPLY', 'ACCEPTED', (int) $booking->sequence);

        $this->withToken('testing-webhook-secret')
            ->postJson(route('api.mail.store'), $this->payload($booking, 'stranger@example.com', $ics))
            ->assertOk();

        $this->assertSame(InboundMailOutcome::Queued, InboundMail::query()->first()?->outcome);
        Mail::assertQueued(UnhandledInboundMail::class, fn (UnhandledInboundMail $mail): bool => $mail->hasTo($manager->email));
    }

    public function test_cancellation_includes_imip_decline(): void
    {
        [$booking, $member] = $this->bookingWithAttendee();
        $manager = User::factory()->teamManager($booking->team)->create();

        $this->withToken('testing-webhook-secret')
            ->postJson(route('api.mail.store'), $this->payload(
                $booking,
                $member->email,
                $this->ics($booking, $member->email, 'REPLY', 'DECLINED', (int) $booking->sequence),
            ))
            ->assertOk();

        $this->assertSame(
            BookingAttendeeStatus::Declined,
            $booking->fresh()->attendees()->find($member->getKey())?->attendance?->status,
        );

        Queue::fake();
        Mail::fake();

        $booking->refresh();
        $booking->status = BookingStatus::Cancelled;
        $booking->cancellationReason = 'Bad weather';
        $booking->save();
        event(new BookingCancelled($booking->fresh(), $manager, 'Bad weather'));

        foreach (Queue::pushed(SendBookingNotification::class) as $job) {
            $job->handle();
        }

        Mail::assertSent(BookingCancelledMail::class, fn (BookingCancelledMail $mail): bool => $mail->hasTo($member->email));
    }

    /**
     * @return array{0: Booking, 1: User}
     */
    private function bookingWithAttendee(BookingAttendeeStatus $status = BookingAttendeeStatus::NeedsAction): array
    {
        $booking = Booking::factory()->create();
        $member = User::factory()->teamMember($booking->team)->create([
            'email' => 'member@example.com',
        ]);
        $booking->attendees()->attach($member->getKey(), [
            'status' => $status->value,
            'token' => 'invite-token',
        ]);

        return [$booking, $member];
    }

    private function postCalendar(
        Booking $booking,
        User $member,
        string $method,
        string $partstat,
        int $sequence,
    ) {
        $ics = $this->ics($booking, $member->email, $method, $partstat, $sequence);

        return $this->withToken('testing-webhook-secret')
            ->postJson(route('api.mail.store'), $this->payload($booking, $member->email, $ics));
    }

    private function ics(Booking $booking, string $email, string $method, string $partstat, int $sequence): string
    {
        $uid = InboundMailAddress::forBooking($booking);

        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'METHOD:'.$method,
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'SEQUENCE:'.$sequence,
            'DTSTAMP:'.now()->format('Ymd\THis\Z'),
            'ATTENDEE;PARTSTAT='.$partstat.':mailto:'.$email,
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Booking $booking, string $from, string $ics): array
    {
        $to = InboundMailAddress::forBooking($booking);

        return [
            'subject' => 'Accepted: '.$booking->activity->name,
            'date' => now()->toIso8601String(),
            'from' => [
                'text' => $from,
                'value' => [['address' => $from, 'name' => 'Member']],
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
        ];
    }
}
