<?php

namespace Tests\Feature;

use App\Actions\PostCommentAction;
use App\Enums\BookingAttendeeStatus;
use App\Enums\MailChannel;
use App\Enums\MembershipRole;
use App\Filament\Member\Resources\Bookings\BookingResource as MemberBookingResource;
use App\Filament\Member\Resources\Inbox\InboxResource;
use App\Filament\Teams\Resources\Bookings\Pages\ViewBooking as TeamsViewBooking;
use App\Livewire\BookingComments;
use App\Mail\BookingCommentMail;
use App\Models\Booking;
use App\Models\Comment;
use App\Models\Team;
use App\Models\User;
use App\Notifications\BookingCommentNotification;
use App\Support\Avatars;
use App\Support\BookingMailThread;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class BookingCommentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_attendee_who_can_respond_can_post_a_comment(): void
    {
        [$manager, $member, $booking] = $this->party();

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Livewire::test(BookingComments::class, ['record' => $booking])
            ->set('body', 'Bring helmets.')
            ->call('post')
            ->assertSee('Bring helmets.')
            ->assertDontSee($member->email);

        $this->assertDatabaseHas('comments', [
            'commentable_id' => $booking->getKey(),
            'author_id' => $member->getKey(),
            'body' => 'Bring helmets.',
        ]);
    }

    public function test_a_guest_an_outsider_and_a_member_who_cannot_respond_cannot_post(): void
    {
        [$manager, $member, $booking, $team] = $this->party();
        $guest = User::factory()->guest()->create();
        $outsider = User::factory()->teamMember(Team::factory()->create())->create();
        $onlooker = User::factory()->create();
        $team->grant($onlooker, MembershipRole::Member);

        Filament::setCurrentPanel('member');

        $this->actingAs($guest);
        Livewire::test(BookingComments::class, ['record' => $booking])->assertForbidden();

        $this->actingAs($outsider);
        Livewire::test(BookingComments::class, ['record' => $booking])->assertForbidden();

        $this->actingAs($onlooker);
        Livewire::test(BookingComments::class, ['record' => $booking])
            ->assertDontSee('Comments')
            ->assertDontSee('Add a comment...')
            ->set('body', 'Should not save')
            ->call('post')
            ->assertForbidden();

        $this->assertDatabaseCount('comments', 0);
        $this->assertFalse($guest->can('create', [Comment::class, $booking]));
        $this->assertFalse($onlooker->can('create', [Comment::class, $booking]));
        $this->assertTrue($member->can('create', [Comment::class, $booking]));
        $this->assertTrue($manager->can('create', [Comment::class, $booking]));
    }

    public function test_a_declined_attendee_can_still_post_because_they_can_respond(): void
    {
        [$manager, $member, $booking] = $this->party();
        $booking->attendees()->syncWithoutDetaching([
            $member->getKey() => ['status' => BookingAttendeeStatus::Declined->value],
        ]);

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Livewire::test(BookingComments::class, ['record' => $booking])
            ->set('body', 'I cannot make it, but here is a note.')
            ->call('post')
            ->assertSee('I cannot make it, but here is a note.');
    }

    public function test_posting_is_refused_on_past_and_cancelled_bookings(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->create();
        $team->grant($member, MembershipRole::Member);
        $past = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'start_at' => now()->subDays(3),
            'end_at' => now()->subDays(3)->addHours(2),
        ]);
        $cancelled = Booking::factory()->cancelled()->create([
            'team_id' => $team->getKey(),
        ]);
        $past->attendees()->attach($member->id, ['status' => BookingAttendeeStatus::Accepted->value]);
        $cancelled->attendees()->attach($member->id, ['status' => BookingAttendeeStatus::Accepted->value]);

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Livewire::test(BookingComments::class, ['record' => $past])
            ->assertDontSee('Comments')
            ->set('body', 'Too late')
            ->call('post')
            ->assertForbidden();

        Livewire::test(BookingComments::class, ['record' => $cancelled])
            ->assertDontSee('Comments')
            ->set('body', 'Too late')
            ->call('post')
            ->assertForbidden();
    }

    public function test_the_author_and_a_manager_see_a_ghosted_trashed_comment_and_others_do_not(): void
    {
        [$manager, $member, $booking, $team] = $this->party();
        $other = User::factory()->create();
        $team->grant($other, MembershipRole::Member);
        $booking->attendees()->attach($other->id, ['status' => BookingAttendeeStatus::Accepted->value]);
        $comment = Comment::factory()->forBooking($booking)->create([
            'author_id' => $member->getKey(),
            'body' => 'Please ignore this.',
        ]);

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Livewire::test(BookingComments::class, ['record' => $booking])
            ->call('remove', $comment->getKey())
            ->assertSee('Please ignore this.')
            ->assertSee('Deleted')
            ->assertDontSee('Edited')
            ->assertSeeHtml('comment-row-ghosted');

        $this->assertSoftDeleted($comment);

        $this->actingAs($manager);
        Livewire::test(BookingComments::class, ['record' => $booking])
            ->assertSee('Please ignore this.')
            ->assertSeeHtml('comment-row-ghosted')
            ->assertSee('Restore');

        $this->actingAs($other);
        Livewire::test(BookingComments::class, ['record' => $booking])
            ->assertDontSee('Please ignore this.');
    }

    public function test_a_manager_can_restore_a_trashed_comment_and_a_member_cannot(): void
    {
        [$manager, $member, $booking] = $this->party();
        $comment = Comment::factory()->forBooking($booking)->trashed()->create([
            'author_id' => $member->getKey(),
            'body' => 'Hold this.',
        ]);

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Livewire::test(BookingComments::class, ['record' => $booking])
            ->call('restore', $comment->getKey())
            ->assertForbidden();

        $this->assertTrue($comment->fresh()->trashed());

        $this->actingAs($manager);
        Livewire::test(BookingComments::class, ['record' => $booking])
            ->call('restore', $comment->getKey())
            ->assertSee('Hold this.')
            ->assertDontSeeHtml('comment-row-ghosted');

        $this->assertFalse($comment->fresh()->trashed());
    }

    public function test_removing_and_restoring_a_comment_does_not_mark_it_as_edited(): void
    {
        $this->travelTo('2026-11-01 15:00:00');

        [$manager, $member, $booking] = $this->party();
        $comment = Comment::factory()->forBooking($booking)->create([
            'author_id' => $member->getKey(),
            'body' => 'Keep the timestamp.',
        ]);

        $this->travelTo('2026-11-01 17:00:00');

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Livewire::test(BookingComments::class, ['record' => $booking])
            ->call('remove', $comment->getKey())
            ->assertSee('Deleted')
            ->assertDontSee('Edited');

        $this->assertSame('2026-11-01 15:00:00', $comment->fresh()->updated_at?->format('Y-m-d H:i:s'));

        $this->actingAs($manager);
        Livewire::test(BookingComments::class, ['record' => $booking])
            ->call('restore', $comment->getKey())
            ->assertSee('Keep the timestamp.')
            ->assertDontSee('Deleted')
            ->assertDontSee('Edited');

        $restored = $comment->fresh();
        $this->assertSame('2026-11-01 15:00:00', $restored->updated_at?->format('Y-m-d H:i:s'));
        $this->assertFalse($restored->trashed());
    }

    public function test_the_author_or_a_manager_can_permanently_remove_a_trashed_comment(): void
    {
        [$manager, $member, $booking] = $this->party();
        $authors = Comment::factory()->forBooking($booking)->trashed()->create([
            'author_id' => $member->getKey(),
            'body' => 'Author trash',
        ]);
        $managed = Comment::factory()->forBooking($booking)->trashed()->create([
            'author_id' => $member->getKey(),
            'body' => 'Manager trash',
        ]);

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Livewire::test(BookingComments::class, ['record' => $booking])
            ->call('remove', $authors->getKey());

        $this->assertModelMissing($authors);

        $this->actingAs($manager);
        Livewire::test(BookingComments::class, ['record' => $booking])
            ->call('remove', $managed->getKey());

        $this->assertModelMissing($managed);
    }

    public function test_a_member_cannot_remove_someone_elses_comment(): void
    {
        [$manager, $member, $booking, $team] = $this->party();
        $other = User::factory()->create();
        $team->grant($other, MembershipRole::Member);
        $booking->attendees()->attach($other->id, ['status' => BookingAttendeeStatus::Accepted->value]);
        $comment = Comment::factory()->forBooking($booking)->create([
            'author_id' => $member->getKey(),
            'body' => 'Mine',
        ]);

        Filament::setCurrentPanel('member');
        $this->actingAs($other);

        Livewire::test(BookingComments::class, ['record' => $booking])
            ->assertDontSee('Remove')
            ->call('remove', $comment->getKey())
            ->assertForbidden();

        $this->assertModelExists($comment);
    }

    public function test_a_comment_time_has_a_readable_datetime_tooltip(): void
    {
        $this->travelTo('2026-11-01 17:32:00');

        [$manager, $member, $booking] = $this->party();
        Comment::factory()->forBooking($booking)->create([
            'author_id' => $member->getKey(),
            'body' => 'Timed',
            'created_at' => '2026-11-01 15:32:00',
            'updated_at' => '2026-11-01 15:32:00',
        ]);

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Livewire::test(BookingComments::class, ['record' => $booking])
            ->assertSee('2 hours ago')
            ->assertSee('1 Nov 2026, 15:32')
            ->assertDontSee('Edited')
            ->assertDontSee('Deleted');
    }

    public function test_the_author_can_edit_a_live_comment_only(): void
    {
        $this->travelTo('2026-11-01 15:00:00');

        [$manager, $member, $booking] = $this->party();
        $live = Comment::factory()->forBooking($booking)->create([
            'author_id' => $member->getKey(),
            'body' => 'Draft',
        ]);
        $trashed = Comment::factory()->forBooking($booking)->trashed()->create([
            'author_id' => $member->getKey(),
            'body' => 'Gone',
        ]);

        $this->travelTo('2026-11-01 15:05:00');

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Livewire::test(BookingComments::class, ['record' => $booking])
            ->call('startEdit', $live->getKey())
            ->set('editBody', 'Final')
            ->call('saveEdit')
            ->assertSee('Final')
            ->assertSee('Edited')
            ->assertDontSee('Draft');

        Livewire::test(BookingComments::class, ['record' => $booking])
            ->call('startEdit', $trashed->getKey())
            ->assertForbidden();

        $this->actingAs($manager);
        Livewire::test(BookingComments::class, ['record' => $booking])
            ->call('startEdit', $live->fresh()->getKey())
            ->assertForbidden();
    }

    public function test_comments_on_another_team_are_isolated(): void
    {
        [$manager, $member, $booking] = $this->party();
        Comment::factory()->forBooking($booking)->create([
            'author_id' => $member->getKey(),
            'body' => 'Team A only',
        ]);
        $otherTeam = Team::factory()->create();
        $otherManager = User::factory()->teamManager($otherTeam)->create();
        $otherBooking = Booking::factory()->confirmed()->create([
            'team_id' => $otherTeam->getKey(),
        ]);

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Livewire::test(BookingComments::class, ['record' => $otherBooking])->assertForbidden();

        $this->actingAs($otherManager);
        Livewire::test(BookingComments::class, ['record' => $otherBooking])
            ->assertDontSee('Team A only');
    }

    public function test_a_system_admin_who_does_not_manage_the_team_cannot_moderate(): void
    {
        [$manager, $member, $booking] = $this->party();
        $admin = User::factory()->systemAdmin()->create();
        $comment = Comment::factory()->forBooking($booking)->trashed()->create([
            'author_id' => $member->getKey(),
        ]);

        $this->assertFalse($admin->can('restore', $comment));
        $this->assertFalse($admin->can('forceDelete', $comment));
        $this->assertFalse($admin->can('delete', Comment::factory()->forBooking($booking)->create([
            'author_id' => $member->getKey(),
        ])));
    }

    public function test_posting_writes_inbox_rows_with_a_comment_query_and_skips_the_author(): void
    {
        Mail::fake();

        [$manager, $member, $booking, $team] = $this->party();
        $declined = User::factory()->create();
        $team->grant($declined, MembershipRole::Member);
        $booking->attendees()->attach($declined->id, ['status' => BookingAttendeeStatus::Declined->value]);
        $absentManager = User::factory()->create();
        $team->grant($absentManager, MembershipRole::Manager);

        Filament::setCurrentPanel('member');
        $comment = (new PostCommentAction)($booking, $member, 'See you at the hut.');

        $this->assertSame(0, $member->notifications()->count());
        $this->assertSame(0, $declined->notifications()->count());

        foreach ([$manager, $absentManager] as $recipient) {
            $row = $recipient->notifications()->first();
            $this->assertNotNull($row);
            $this->assertSame(BookingCommentNotification::class, $row->type);
            $this->assertSame(MailChannel::BookingsComments->value, $row->data['mail_channel']);
            $this->assertStringContainsString('comment='.$comment->getKey(), $row->data['url']);
            $this->assertStringContainsString((string) $booking->getKey(), $row->data['url']);
            $this->assertNotNull($row->read_at);
            $this->assertNotNull($row->digest_notified_at);
        }

        $this->actingAs($manager);
        $this->assertSame(0, InboxResource::unreadCount());
    }

    public function test_comment_mail_is_queued_when_comments_are_on_and_skipped_when_they_are_off(): void
    {
        Mail::fake();

        [$manager, $member, $booking] = $this->party();

        Filament::setCurrentPanel('member');
        (new PostCommentAction)($booking, $member, 'Helmets in the porch.');

        Mail::assertQueued(BookingCommentMail::class, function (BookingCommentMail $mail) use ($manager): bool {
            return $mail->hasTo($manager->email);
        });
        Mail::assertNotQueued(BookingCommentMail::class, function (BookingCommentMail $mail) use ($member): bool {
            return $mail->hasTo($member->email);
        });

        Mail::fake();
        $manager->forceFill([
            'mail_preferences' => [
                MailChannel::BookingsComments->value => false,
            ],
        ])->save();

        $quiet = (new PostCommentAction)($booking, $member, 'Quiet please.');

        Mail::assertNothingOutgoing();
        $row = $manager->notifications()->get()->first(
            fn ($notification): bool => ($notification->data['rebuild']['comment_id'] ?? null) === $quiet->getKey(),
        );
        $this->assertNotNull($row);
        $this->assertNull($row->read_at);
        $this->assertNull($row->digest_notified_at);

        $this->actingAs($manager);
        $this->assertSame(1, InboxResource::unreadCount());

        Mail::fake();
        $manager->forceFill([
            'mail_preferences' => [
                MailChannel::BookingsMail->value => false,
                MailChannel::BookingsComments->value => true,
            ],
        ])->save();

        (new PostCommentAction)($booking, $member, 'Still quiet.');

        Mail::assertNothingOutgoing();
    }

    public function test_comment_mail_threads_with_the_booking_and_has_no_unsubscribe_header(): void
    {
        [$manager, $member, $booking] = $this->party();
        $comment = Comment::factory()->forBooking($booking)->create([
            'author_id' => $member->getKey(),
            'body' => 'Bring a stove.',
        ]);
        $mail = new BookingCommentMail($comment, $manager);
        $reference = '<'.BookingMailThread::reference($booking).'>';

        $mail->assertHasSubject(BookingMailThread::subject($booking, 'Re'));
        $this->assertSame($reference, $mail->headers()->referencesString());
        $this->assertSame($reference, $mail->headers()->text['In-Reply-To']);
        $this->assertArrayNotHasKey('List-Unsubscribe', $mail->headers()->text);

        $html = $mail->render();
        $this->assertStringContainsString('Bring a stove.', $html);
        $this->assertStringContainsString('comment='.$comment->getKey(), $html);
        $this->assertStringContainsString('Manage notifications for this booking', $html);
    }

    public function test_opening_a_comment_query_puts_that_row_first_and_hides_older_ones_until_shown(): void
    {
        [$manager, $member, $booking] = $this->party();
        $older = Comment::factory()->forBooking($booking)->create([
            'author_id' => $member->getKey(),
            'body' => 'Oldest note',
            'created_at' => now()->subMinutes(3),
        ]);
        $target = Comment::factory()->forBooking($booking)->create([
            'author_id' => $member->getKey(),
            'body' => 'Linked note',
            'created_at' => now()->subMinutes(2),
        ]);
        Comment::factory()->forBooking($booking)->create([
            'author_id' => $member->getKey(),
            'body' => 'Newest note',
            'created_at' => now()->subMinute(),
        ]);

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Livewire::test(BookingComments::class, [
            'record' => $booking,
            'highlightId' => $target->getKey(),
        ])
            ->assertSeeInOrder(['Linked note', 'Newest note'])
            ->assertDontSee('Oldest note')
            ->assertSee('Show older comments')
            ->assertSeeHtml('id="comment-'.$target->getKey().'"')
            ->call('showOlderComments')
            ->assertSeeInOrder(['Oldest note', 'Linked note', 'Newest note']);

        Livewire::test(BookingComments::class, [
            'record' => $booking,
            'highlightId' => 'missing',
        ])
            ->assertSee('Oldest note')
            ->assertDontSee('Show older comments');

        $this->assertModelExists($older);
    }

    public function test_an_unauthenticated_visit_of_a_comment_link_redirects_to_login_then_the_booking(): void
    {
        [$manager, $member, $booking] = $this->party();
        $comment = Comment::factory()->forBooking($booking)->create([
            'author_id' => $member->getKey(),
            'body' => 'From the email',
        ]);

        Filament::setCurrentPanel('member');
        $url = MemberBookingResource::viewUrl($booking)
            .'?comment='.$comment->getKey();

        $this->get($url)->assertRedirect(Filament::getPanel('teams')->getLoginUrl());
        $this->assertStringContainsString('comment='.$comment->getKey(), (string) session('url.intended'));

        $this->actingAs($member)
            ->get((string) session('url.intended'))
            ->assertOk()
            ->assertSee('From the email');
    }

    public function test_the_feed_marks_lead_and_manager_and_never_shows_an_email(): void
    {
        [$manager, $member, $booking] = $this->party();
        $booking->forceFill(['lead_instructor_id' => $member->getKey()])->saveQuietly();
        Comment::factory()->forBooking($booking)->create([
            'author_id' => $member->getKey(),
            'body' => 'Lead note',
        ]);
        Comment::factory()->forBooking($booking)->create([
            'author_id' => $manager->getKey(),
            'body' => 'Manager note',
        ]);

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Livewire::test(BookingComments::class, ['record' => $booking])
            ->assertSee('Lead')
            ->assertSee('Manager')
            ->assertSee('Lead note')
            ->assertSee('Manager note')
            ->assertDontSee($member->email)
            ->assertDontSee($manager->email)
            ->assertSeeHtml(hash('sha256', strtolower(trim($member->email))));
    }

    public function test_the_composer_looks_like_an_unwritten_comment_when_the_viewer_can_post(): void
    {
        [$manager, $member, $booking] = $this->party();

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Livewire::test(BookingComments::class, ['record' => $booking])
            ->assertSee('Comments')
            ->assertSee($member->name)
            ->assertSee('Add a comment...')
            ->assertSee('Post')
            ->assertDontSee('Post comment')
            ->assertDontSee('No comments yet.');
    }

    public function test_a_closed_booking_still_shows_existing_comments(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->create();
        $team->grant($member, MembershipRole::Member);
        $past = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'start_at' => now()->subDays(3),
            'end_at' => now()->subDays(3)->addHours(2),
        ]);
        $past->attendees()->attach($member->id, ['status' => BookingAttendeeStatus::Accepted->value]);
        Comment::factory()->forBooking($past)->create([
            'author_id' => $member->getKey(),
            'body' => 'We left the mats out.',
        ]);

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Livewire::test(BookingComments::class, ['record' => $past])
            ->assertSee('Comments')
            ->assertSee('We left the mats out.')
            ->assertDontSee('Add a comment...');
    }

    public function test_a_past_booking_without_comments_does_not_leave_a_comments_slot_on_the_view(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->create();
        $team->grant($member, MembershipRole::Member);
        $past = Booking::factory()->confirmed()->forTeam($team)->create([
            'start_at' => now()->subDays(3),
            'end_at' => now()->subDays(3)->addHours(2),
        ]);
        $past->attendees()->attach($member->id, ['status' => BookingAttendeeStatus::Accepted->value]);

        $this->assertFalse(BookingComments::shouldShowPanel($past, $member));

        $this->actingAs($member);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(TeamsViewBooking::class, ['record' => $past->getRouteKey()])
            ->assertSee('Recent activity')
            ->assertDontSeeLivewire(BookingComments::class)
            ->assertDontSee('Comments');
    }

    public function test_comments_render_filament_user_avatars_from_gravatar(): void
    {
        [, $member, $booking] = $this->party();
        $member->update(['email' => 'Ada@Example.COM']);
        Comment::factory()->forBooking($booking)->create([
            'author_id' => $member->getKey(),
            'body' => 'Bring helmets.',
        ]);

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Livewire::test(BookingComments::class, ['record' => $booking])
            ->assertSee('Bring helmets.')
            ->assertSee('fi-user-avatar', false)
            ->assertSee(
                str_replace('&', '&amp;', Avatars::userUrl($member->fresh())),
                false,
            )
            ->assertDontSee('comment-avatar', false);
    }

    public function test_manage_and_member_booking_views_show_comments_under_the_details(): void
    {
        [$manager, $member, $booking] = $this->party();
        Comment::factory()->forBooking($booking)->create([
            'author_id' => $member->getKey(),
            'body' => 'On the booking',
        ]);

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);

        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('Comments')
            ->assertSee('On the booking')
            ->assertSee('Recent activity');

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        Filament::setCurrentPanel('teams');
        Filament::setTenant($booking->team);
        Livewire::test(TeamsViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('Comments')
            ->assertSee('On the booking')
            ->assertSee('Recent activity');
    }

    /**
     * @return array{0: User, 1: User, 2: Booking, 3: Team}
     */
    private function party(): array
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $member = User::factory()->create();
        $team->grant($member, MembershipRole::Member);
        $booking = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
        ]);
        $booking->attendees()->attach($member->id, ['status' => BookingAttendeeStatus::Accepted->value]);
        $booking->attendees()->attach($manager->id, ['status' => BookingAttendeeStatus::Accepted->value]);

        return [$manager, $member, $booking, $team];
    }
}
