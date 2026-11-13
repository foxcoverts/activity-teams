<?php

namespace Tests\Feature;

use App\Actions\DeleteUserAction;
use App\Actions\ResendBookingIcsAction;
use App\Enums\BookingAttendeeStatus;
use App\Enums\InboundMailOutcome;
use App\Enums\MailChannel;
use App\Enums\MailGroup;
use App\Enums\MembershipRole;
use App\Events\BookingInvite;
use App\Filament\Admin\Resources\Teams\TeamResource;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Filament\Teams\Resources\Keys\KeyResource;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Jobs\SendBookingNotification;
use App\Mail\BookingIcsRefreshMail;
use App\Mail\BookingInviteMail;
use App\Mail\CombinedDigestMail;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\InboundMail;
use App\Models\Key;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\BookingHeldNotification;
use App\Notifications\EmailBouncedNotification;
use App\Notifications\InboundReviewNotification;
use App\Notifications\KeyHolderLeavingNotification;
use App\Notifications\KeyPassedToNotification;
use App\Notifications\LastManagerLeavingNotification;
use App\Notifications\TeamInvitationDeclinedNotification;
use App\Notifications\TeamInvitationNotification;
use App\Support\CombinedDigest;
use App\Support\InboundReviewNudge;
use App\Support\MailPreference;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InboxDigestTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_digest_is_scheduled_daily_without_overlapping(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn (object $event): bool => str_contains((string) $event->description, 'SendCombinedDigest'));

        $this->assertNotNull($event);
        $this->assertSame('0 0 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_a_single_outstanding_item_emails_the_existing_mail_and_marks_it_read(): void
    {
        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->create(['name' => 'Bounced Member']);
        $manager->managedTeams()->first()->grant($member, MembershipRole::Member);

        $manager->notify(new EmailBouncedNotification($member));

        app(CombinedDigest::class)->send($manager);

        $sent = app('mail.manager')->mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $message = $sent->first()->getOriginalMessage();
        $this->assertStringContainsString('Email delivery failed', $message->getSubject());
        $this->assertStringContainsString('Bounced Member', $message->getHtmlBody());

        $row = $manager->notifications()->first();
        $this->assertNotNull($row->read_at);
        $this->assertNotNull($row->digest_notified_at);
        $this->assertNotNull($row->outbound_message_id);
    }

    public function test_a_row_read_before_the_run_is_left_out_and_a_second_empty_run_sends_nothing(): void
    {
        Mail::fake();

        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->create();
        $manager->managedTeams()->first()->grant($member, MembershipRole::Member);
        $manager->notify(new EmailBouncedNotification($member));
        $manager->notifications()->first()->markReadForDelivery();

        app(CombinedDigest::class)->sendAll();

        Mail::assertNothingOutgoing();

        Mail::fake();
        app(CombinedDigest::class)->sendAll();

        Mail::assertNothingOutgoing();
    }

    public function test_several_outstanding_items_send_a_summary_that_does_not_mark_them_read(): void
    {
        Mail::fake();

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $members = collect(range(1, 3))->map(function () use ($team): User {
            $member = User::factory()->create();
            $team->grant($member, MembershipRole::Member);

            return $member;
        });

        foreach ($members as $member) {
            $manager->notify(new EmailBouncedNotification($member));
        }

        $manager->notifications()->reorder()->orderBy('created_at')->first()->forceFill(['digest_notified_at' => now()])->save();

        app(CombinedDigest::class)->send($manager);

        Mail::assertQueued(CombinedDigestMail::class, function (CombinedDigestMail $mail): bool {
            return $mail->outstanding === 2
                && $mail->unreadRemainder === 1
                && $mail->rota === null
                && str_contains($mail->render(), 'You have 2 new notifications')
                && str_contains($mail->render(), '1 unread notification from earlier');
        });

        $manager->notifications()->each(function ($row): void {
            $this->assertNull($row->read_at);
        });
        $this->assertSame(0, $manager->notifications()->whereNull('digest_notified_at')->count());
    }

    public function test_a_rota_change_without_emailable_inbox_rows_sends_only_the_rota(): void
    {
        Mail::fake();

        $member = User::factory()->teamMember()->create();
        $booking = Booking::factory()->create();
        $this->accept($booking, $member);

        app(CombinedDigest::class)->send($member);
        Mail::assertNothingOutgoing();

        $added = Booking::factory()->create(['team_id' => $booking->team_id]);
        $this->accept($added, $member);

        Mail::fake();
        app(CombinedDigest::class)->send($member);

        Mail::assertQueued(CombinedDigestMail::class, function (CombinedDigestMail $mail) use ($added): bool {
            return $mail->outstanding === null
                && $mail->unreadRemainder === 0
                && str_contains($mail->envelope()->subject, 'Your rota')
                && str_contains($mail->render(), $added->activity->name)
                && ! str_contains($mail->render(), 'new notification');
        });
    }

    public function test_turning_the_digest_off_advances_the_snapshot_without_emailing_a_backlog(): void
    {
        Mail::fake();

        $member = User::factory()->teamMember()->create();
        MailPreference::save($member, MailGroup::Bookings, [
            MailChannel::BookingsDigest->value => '0',
        ]);

        $booking = Booking::factory()->create();
        $this->accept($booking, $member);
        app(CombinedDigest::class)->send($member);

        $added = Booking::factory()->create(['team_id' => $booking->team_id]);
        $this->accept($added, $member);
        app(CombinedDigest::class)->send($member);

        MailPreference::save($member->fresh(), MailGroup::Bookings, [
            MailChannel::BookingsDigest->value => '1',
        ]);

        Mail::fake();
        app(CombinedDigest::class)->send($member->fresh());

        Mail::assertNothingOutgoing();
    }

    public function test_a_preference_off_row_is_stored_and_does_not_send_on_its_own(): void
    {
        Mail::fake();

        $receiver = User::factory()->teamMember()->create();
        $ring = Key::factory()->create();
        $holder = User::factory()->create();

        MailPreference::save($receiver, MailGroup::Keys, [
            MailChannel::KeysMail->value => '0',
        ]);

        $receiver->notify(new KeyPassedToNotification($ring, $holder));

        $row = $receiver->notifications()->first();
        $this->assertNotNull($row);

        app(CombinedDigest::class)->send($receiver);

        Mail::assertNothingOutgoing();
        $this->assertNull($row->fresh()->read_at);
        $this->assertNull($row->fresh()->digest_notified_at);
    }

    public function test_a_blocked_booking_invite_is_an_inbox_row_without_an_ics_mail(): void
    {
        [$booking, $attendee] = $this->invitedBooking();

        MailPreference::save($attendee, MailGroup::Bookings, [
            MailChannel::BookingsMail->value => '0',
        ], $booking);

        Queue::fake();
        Mail::fake();

        event(new BookingInvite($booking, $attendee->fresh()));

        foreach (Queue::pushed(SendBookingNotification::class) as $job) {
            $job->handle();
        }

        Mail::assertNotSent(BookingInviteMail::class);

        $row = $attendee->notifications()->where('type', BookingHeldNotification::class)->first();
        $this->assertNotNull($row);
        $this->assertTrue($row->data['in_app_only']);
        $this->assertStringContainsString($booking->activity->name, $row->data['body']);
    }

    public function test_a_blocked_calendar_refresh_writes_an_inbox_row_and_sends_no_ics(): void
    {
        Mail::fake();

        $attendee = User::factory()->teamMember()->create();
        $booking = Booking::factory()->create();
        $this->accept($booking, $attendee);

        MailPreference::save($attendee, MailGroup::Bookings, [
            MailChannel::BookingsMail->value => '0',
        ], $booking);

        app(ResendBookingIcsAction::class)($booking, $attendee);

        Mail::assertNothingOutgoing();
        $this->assertTrue($attendee->notifications()->where('type', BookingHeldNotification::class)->exists());
    }

    public function test_a_forced_calendar_refresh_sends_ics_when_mail_is_off(): void
    {
        Mail::fake();

        $attendee = User::factory()->teamMember()->create();
        $booking = Booking::factory()->create();
        $this->accept($booking, $attendee);

        MailPreference::save($attendee, MailGroup::Bookings, [
            MailChannel::BookingsMail->value => '0',
        ], $booking);

        app(ResendBookingIcsAction::class)($booking, $attendee, force: true);

        Mail::assertQueued(BookingIcsRefreshMail::class);
        $this->assertFalse($attendee->notifications()->where('type', BookingHeldNotification::class)->exists());
    }

    public function test_an_allowed_invite_still_sends_immediately(): void
    {
        [$booking, $attendee] = $this->invitedBooking();

        Queue::fake();
        Mail::fake();

        event(new BookingInvite($booking, $attendee->fresh()));

        foreach (Queue::pushed(SendBookingNotification::class) as $job) {
            $job->handle();
        }

        Mail::assertSent(BookingInviteMail::class, fn (BookingInviteMail $mail): bool => $mail->hasTo($attendee->email));
        $this->assertFalse($attendee->notifications()->where('type', BookingHeldNotification::class)->exists());
    }

    public function test_an_invitation_stays_on_immediate_mail(): void
    {
        $invitation = TeamInvitation::factory()->create();
        $notification = new TeamInvitationNotification($invitation);

        $this->assertSame(['mail'], $notification->via($invitation->user));
    }

    public function test_a_correlated_bounce_clears_the_read_markers(): void
    {
        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->create();
        $manager->managedTeams()->first()->grant($member, MembershipRole::Member);
        $manager->notify(new EmailBouncedNotification($member));

        Mail::fake();
        app(CombinedDigest::class)->send($manager);

        $row = $manager->notifications()->first();
        $this->assertNotNull($row->read_at);
        $row->releaseAfterBounce();
        $row->refresh();

        $this->assertNull($row->read_at);
        $this->assertNull($row->digest_notified_at);
    }

    public function test_last_manager_leaving_links_to_teams_with_no_manager(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $manager = User::factory()->teamManager()->create(['name' => 'Last Manager']);
        $team = $manager->managedTeams()->first();

        app(DeleteUserAction::class)->delete($manager, $manager, 'password');

        $row = $admin->notifications()->where('type', LastManagerLeavingNotification::class)->first();

        $this->assertNotNull($row);
        $this->assertStringContainsString('Last Manager', $row->data['body']);
        $this->assertStringContainsString($team->name, $row->data['body']);
        $this->assertSame(TeamResource::getUrl('index', [
            'filters' => [
                'managers.id' => ['value' => '__empty'],
            ],
        ], panel: 'admin'), $row->data['url']);
    }

    public function test_key_holder_leaving_links_each_team_to_that_holder(): void
    {
        $first = Team::factory()->create();
        $second = Team::factory()->create();
        $manager = User::factory()->teamManager($first)->create();
        $second->grant($manager, MembershipRole::Manager);
        $holder = User::factory()->teamMember($first)->create(['name' => 'Key Holder']);
        $second->grant($holder, MembershipRole::Member);
        $ring = Key::factory()->heldBy($holder)->create(['name' => 'Store keys']);

        app(DeleteUserAction::class)->delete($holder, $holder, 'password');

        $rows = $manager->notifications()->where('type', KeyHolderLeavingNotification::class)->get();

        $this->assertCount(2, $rows);

        $urls = $rows->map(fn ($row) => $row->data['url'])->all();

        $this->assertContains(KeyResource::getUrl('index', [
            'filters' => [
                'holder_id' => ['value' => $holder->getKey()],
            ],
        ], panel: 'teams', tenant: $first), $urls);
        $this->assertContains(KeyResource::getUrl('index', [
            'filters' => [
                'holder_id' => ['value' => $holder->getKey()],
            ],
        ], panel: 'teams', tenant: $second), $urls);
        $this->assertStringContainsString('Store keys', $rows->first()->data['body']);
        $this->assertStringContainsString('Key Holder', $rows->first()->data['body']);
    }

    public function test_a_declined_team_invitation_links_to_the_placeholder_member(): void
    {
        $invitation = TeamInvitation::factory()->declined()->create();
        $manager = User::factory()->teamManager($invitation->team)->create();

        $manager->notify(new TeamInvitationDeclinedNotification($invitation));

        $row = $manager->notifications()->first();

        $this->assertSame(
            MemberResource::getUrl('view', ['record' => $invitation->user], panel: 'teams', tenant: $invitation->team),
            $row->data['url'],
        );
        $this->assertStringContainsString($invitation->user->name, $row->data['body']);
    }

    public function test_a_declined_site_invitation_links_to_the_admin_user(): void
    {
        $invitation = TeamInvitation::factory()->siteAccess()->declined()->create();
        $admin = User::factory()->systemAdmin()->create();

        $admin->notify(new TeamInvitationDeclinedNotification($invitation));

        $row = $admin->notifications()->first();

        $this->assertSame(
            UserResource::getUrl('view', ['record' => $invitation->user], panel: 'admin'),
            $row->data['url'],
        );
        $this->assertStringContainsString('system administrator', $row->data['body']);
    }

    public function test_unhandled_inbound_mail_coalesces_one_nudge_and_stays_quiet_until_a_newer_message(): void
    {
        Mail::fake();

        $admin = User::factory()->systemAdmin()->create();
        $other = User::factory()->create(['is_system_admin' => true, 'suspended' => true]);
        InboundMail::factory()->create();

        app(InboundReviewNudge::class)->record();
        app(InboundReviewNudge::class)->record();

        $this->assertSame(1, $admin->notifications()->where('type', InboundReviewNotification::class)->count());
        $this->assertSame(0, $other->notifications()->count());
        $this->assertStringContainsString('1 message needs review', $admin->notifications()->first()->data['body']);

        InboundMail::factory()->create();
        app(InboundReviewNudge::class)->record();

        $this->assertSame(1, $admin->notifications()->count());
        $this->assertStringContainsString('2 messages need review', $admin->notifications()->first()->data['body']);

        app(CombinedDigest::class)->send($admin);

        $this->assertNotNull($admin->notifications()->first()->read_at);

        app(InboundReviewNudge::class)->record();
        $this->assertSame(1, $admin->notifications()->count());

        InboundMail::factory()->create(['outcome' => InboundMailOutcome::Failed]);
        app(InboundReviewNudge::class)->record();

        $this->assertSame(2, $admin->notifications()->count());
        $this->assertStringContainsString(
            '3 messages need review',
            $admin->unreadNotifications()->first()->data['body'],
        );
    }

    private function accept(Booking $booking, User $user): void
    {
        $booking->attendees()->attach($user->getKey(), [
            'status' => BookingAttendeeStatus::Accepted->value,
            'token' => BookingAttendance::generateToken(),
            // Avoid US-16 session reminders interfering with rota-only digest assertions.
            'session_reminded_at' => now(),
        ]);
    }

    /**
     * @return array{0: Booking, 1: User}
     */
    private function invitedBooking(): array
    {
        $attendee = User::factory()->teamMember()->create();
        $booking = Booking::factory()->create();
        $booking->attendees()->attach($attendee->getKey(), [
            'status' => BookingAttendeeStatus::NeedsAction->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        return [$booking, $attendee];
    }
}
