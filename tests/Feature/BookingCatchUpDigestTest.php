<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeStatus;
use App\Enums\InvitationNudgeTier;
use App\Enums\MailChannel;
use App\Enums\MailGroup;
use App\Filament\Member\Pages\Dashboard;
use App\Mail\BookingInvitationNudgeMail;
use App\Mail\BookingSessionReminderMail;
use App\Mail\CombinedDigestMail;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use App\Notifications\LeadNeededNotification;
use App\Support\BookingCatchUp;
use App\Support\BookingMailThread;
use App\Support\CombinedDigest;
use App\Support\MailPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingCatchUpDigestTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_at_fifty_seven_days_does_not_nudge_the_next_day(): void
    {
        Mail::fake();

        $member = User::factory()->teamMember()->create();
        $booking = $this->futureBooking(57);
        $this->invite($booking, $member, ageDays: 0);

        $this->travel(1)->days();

        app(CombinedDigest::class)->send($member);

        Mail::assertNothingOutgoing();
        $this->assertNull($this->attendance($booking, $member)->invitation_nudge_tier);
    }

    public function test_month_tier_fires_when_window_and_age_match(): void
    {
        Mail::fake();

        $member = User::factory()->teamMember()->create();
        $booking = $this->futureBooking(20);
        $this->invite($booking, $member, ageDays: 15);

        app(CombinedDigest::class)->send($member);

        Mail::assertSent(BookingInvitationNudgeMail::class, function (BookingInvitationNudgeMail $mail) use ($booking, $member): bool {
            return $mail->booking->is($booking)
                && $mail->attendee->is($member)
                && $mail->envelope()->subject === BookingMailThread::subject($booking, __('Reminder'))
                && str_contains($mail->render(), 'Please respond to this booking invitation');
        });

        $this->assertSame(
            InvitationNudgeTier::Month,
            $this->attendance($booking, $member)->invitation_nudge_tier,
        );
    }

    public function test_late_invite_skips_wider_tiers_and_only_sends_week(): void
    {
        Mail::fake();

        $member = User::factory()->teamMember()->create();
        $booking = $this->futureBooking(10);
        $this->invite($booking, $member, ageDays: 4);

        app(CombinedDigest::class)->send($member);

        Mail::assertNothingOutgoing();

        $this->travel(4)->days();
        Mail::fake();
        app(CombinedDigest::class)->send($member->fresh());

        Mail::assertSent(BookingInvitationNudgeMail::class);
        $this->assertSame(
            InvitationNudgeTier::Week,
            $this->attendance($booking, $member)->invitation_nudge_tier,
        );
    }

    public function test_no_nudge_after_week_tier(): void
    {
        Mail::fake();

        $member = User::factory()->teamMember()->create();
        $booking = $this->futureBooking(5);
        $this->invite($booking, $member, ageDays: 10, tier: InvitationNudgeTier::Week);

        app(CombinedDigest::class)->send($member);

        Mail::assertNothingOutgoing();
    }

    public function test_session_reminder_sends_once_within_a_week(): void
    {
        Mail::fake();

        $member = User::factory()->teamMember()->create();
        $booking = $this->futureBooking(5);
        $this->accept($booking, $member);

        app(CombinedDigest::class)->send($member);

        Mail::assertSent(BookingSessionReminderMail::class, function (BookingSessionReminderMail $mail) use ($booking): bool {
            return $mail->booking->is($booking)
                && str_contains($mail->render(), 'You said you can help with this booking');
        });

        $this->assertNotNull($this->attendance($booking, $member)->session_reminded_at);

        Mail::fake();
        app(CombinedDigest::class)->send($member->fresh());
        Mail::assertNothingOutgoing();
    }

    public function test_bookings_mail_off_skips_nudges(): void
    {
        Mail::fake();

        $member = User::factory()->teamMember()->create();
        MailPreference::save($member, MailGroup::Bookings, [
            MailChannel::BookingsMail->value => '0',
        ]);

        $booking = $this->futureBooking(20);
        $this->invite($booking, $member, ageDays: 15);

        app(CombinedDigest::class)->send($member->fresh());

        Mail::assertNothingOutgoing();
        $this->assertNull($this->attendance($booking, $member)->invitation_nudge_tier);
    }

    public function test_bookings_reminders_off_skips_session_reminders(): void
    {
        Mail::fake();

        $member = User::factory()->teamMember()->create();
        MailPreference::save($member, MailGroup::Bookings, [
            MailChannel::BookingsReminders->value => '0',
        ]);

        $seed = $this->futureBooking(40);
        $this->accept($seed, $member);
        app(CombinedDigest::class)->send($member);
        Mail::fake();

        $reminderBooking = $this->futureBooking(5);
        $this->accept($reminderBooking, $member);
        app(CombinedDigest::class)->send($member->fresh());

        Mail::assertNotSent(BookingSessionReminderMail::class);
        $this->assertNull($this->attendance($reminderBooking, $member)->session_reminded_at);
    }

    public function test_nudge_plus_inbox_sends_summary_with_dashboard_link(): void
    {
        Mail::fake();

        $member = User::factory()->teamManager()->create();
        $teamId = $member->managedTeams()->first()->getKey();
        $leadBooking = Booking::factory()->create(['team_id' => $teamId]);
        $member->notify(new LeadNeededNotification($leadBooking, null));

        $nudgeBooking = $this->futureBooking(20, $teamId);
        $this->invite($nudgeBooking, $member, ageDays: 15);

        app(CombinedDigest::class)->send($member);

        Mail::assertQueued(CombinedDigestMail::class, function (CombinedDigestMail $mail) use ($nudgeBooking): bool {
            return $mail->outstanding === 1
                && is_array($mail->invitationNudges)
                && str_contains($mail->invitationNudges[0], $nudgeBooking->activity->name)
                && str_contains($mail->render(), 'Invitations')
                && str_contains($mail->render(), 'You still need to reply to these invitations.')
                && str_contains($mail->render(), 'View invitations')
                && str_contains($mail->render(), Dashboard::getUrl(panel: 'member'));
        });

        $this->assertSame(
            InvitationNudgeTier::Month,
            $this->attendance($nudgeBooking, $member)->invitation_nudge_tier,
        );
    }

    public function test_send_all_includes_nudge_candidates(): void
    {
        Mail::fake();

        $member = User::factory()->teamMember()->create();
        $booking = $this->futureBooking(20);
        $this->invite($booking, $member, ageDays: 15);

        app(CombinedDigest::class)->sendAll();

        Mail::assertSent(BookingInvitationNudgeMail::class);
    }

    public function test_due_tier_helper_respects_bands_and_age(): void
    {
        $member = User::factory()->teamMember()->create();
        $booking = $this->futureBooking(50);
        $this->invite($booking, $member, ageDays: 1);
        $attendance = $this->attendance($booking, $member);

        $this->assertNull(app(BookingCatchUp::class)->dueInvitationTier($attendance, $booking));

        $this->ageAttendance($booking, $member, 30);
        $attendance = $this->attendance($booking, $member);

        $this->assertSame(
            InvitationNudgeTier::TwoMonths,
            app(BookingCatchUp::class)->dueInvitationTier($attendance, $booking),
        );
    }

    public function test_a_fresh_maybe_response_does_not_get_an_immediate_nudge(): void
    {
        Mail::fake();

        $member = User::factory()->teamMember()->create();
        $booking = $this->futureBooking(20);
        $this->invite($booking, $member, ageDays: 20);

        BookingAttendance::query()
            ->where('booking_id', $booking->getKey())
            ->where('user_id', $member->getKey())
            ->update([
                'status' => BookingAttendeeStatus::Tentative->value,
                'updated_at' => now(),
            ]);

        app(CombinedDigest::class)->send($member);

        Mail::assertNothingOutgoing();
        $this->assertNull($this->attendance($booking, $member)->invitation_nudge_tier);
    }

    private function futureBooking(int $daysUntilStart, ?string $teamId = null): Booking
    {
        $start = now()->addDays($daysUntilStart)->seconds(0);

        return Booking::factory()->create([
            ...($teamId !== null ? ['team_id' => $teamId] : []),
            'start_at' => $start,
            'end_at' => $start->copy()->addHours(2),
        ]);
    }

    private function invite(
        Booking $booking,
        User $user,
        int $ageDays = 0,
        ?InvitationNudgeTier $tier = null,
    ): void {
        $booking->attendees()->attach($user->getKey(), [
            'status' => BookingAttendeeStatus::NeedsAction->value,
            'token' => BookingAttendance::generateToken(),
            'invitation_nudge_tier' => $tier?->value,
        ]);

        $this->ageAttendance($booking, $user, $ageDays);
    }

    private function accept(Booking $booking, User $user): void
    {
        $booking->attendees()->attach($user->getKey(), [
            'status' => BookingAttendeeStatus::Accepted->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        $this->ageAttendance($booking, $user, 14);
    }

    private function ageAttendance(Booking $booking, User $user, int $ageDays): void
    {
        BookingAttendance::query()
            ->where('booking_id', $booking->getKey())
            ->where('user_id', $user->getKey())
            ->update([
                'created_at' => now()->subDays($ageDays),
                'updated_at' => now()->subDays($ageDays),
            ]);
    }

    private function attendance(Booking $booking, User $user): BookingAttendance
    {
        return BookingAttendance::query()
            ->where('booking_id', $booking->getKey())
            ->where('user_id', $user->getKey())
            ->firstOrFail();
    }
}
