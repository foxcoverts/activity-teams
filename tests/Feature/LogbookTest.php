<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeStatus;
use App\Enums\MailChannel;
use App\Filament\Bookings\LogbookEntryAction;
use App\Filament\Member\Pages\Logbook as LogbookPage;
use App\Filament\Teams\Resources\Bookings\Pages\ViewBooking;
use App\Mail\BookingLogbookNudgeMail;
use App\Mail\CombinedDigestMail;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\BookingDebrief;
use App\Models\LogbookEntry;
use App\Models\Team;
use App\Models\User;
use App\Support\BookingWhen;
use App\Support\CombinedDigest;
use App\Support\Logbook;
use App\Support\MailPreference;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class LogbookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('member');
    }

    public function test_outstanding_includes_accepted_due_sessions_only(): void
    {
        $member = User::factory()->teamMember()->create();
        $accepted = $this->pastBooking();
        $tentative = $this->pastBooking();
        $ignored = $this->pastBooking();
        $withEntry = $this->pastBooking();
        $future = $this->futureBooking();

        $this->attend($accepted, $member, BookingAttendeeStatus::Accepted);
        $this->attend($tentative, $member, BookingAttendeeStatus::Tentative);
        $this->attend($ignored, $member, BookingAttendeeStatus::Accepted);
        $this->attend($withEntry, $member, BookingAttendeeStatus::Accepted);
        $this->attend($future, $member, BookingAttendeeStatus::Accepted);

        $ignored->attendees()->syncWithoutDetaching([
            $member->getKey() => ['logbook_ignored_at' => now()],
        ]);

        LogbookEntry::factory()->create([
            'user_id' => $member->getKey(),
            'booking_id' => $withEntry->getKey(),
        ]);

        $outstanding = app(Logbook::class)->outstanding($member);

        $this->assertTrue($outstanding->contains(fn (Booking $booking): bool => $booking->is($accepted)));
        $this->assertFalse($outstanding->contains(fn (Booking $booking): bool => $booking->is($tentative)));
        $this->assertFalse($outstanding->contains(fn (Booking $booking): bool => $booking->is($ignored)));
        $this->assertFalse($outstanding->contains(fn (Booking $booking): bool => $booking->is($withEntry)));
        $this->assertFalse($outstanding->contains(fn (Booking $booking): bool => $booking->is($future)));
    }

    public function test_backfill_creates_empty_entries_that_are_not_outstanding(): void
    {
        $member = User::factory()->teamMember()->create();
        $booking = $this->pastBooking();
        $this->attend($booking, $member, BookingAttendeeStatus::Accepted);

        $this->assertTrue(app(Logbook::class)->outstanding($member)->contains(
            fn (Booking $candidate): bool => $candidate->is($booking),
        ));

        $migration = require database_path('migrations/2026_11_14_115448_create_logbook_entries_for_existing_accepted_attendance.php');
        $migration->up();

        $this->assertDatabaseHas('logbook_entries', [
            'user_id' => $member->getKey(),
            'booking_id' => $booking->getKey(),
            'hours' => null,
            'notes' => null,
        ]);
        $this->assertTrue(app(Logbook::class)->outstanding($member)->isEmpty());
    }

    public function test_add_prefills_from_debrief_without_notes(): void
    {
        $member = User::factory()->teamMember()->create();
        $booking = $this->pastBooking();
        $this->attend($booking, $member, BookingAttendeeStatus::Accepted);

        BookingDebrief::factory()->create([
            'booking_id' => $booking->getKey(),
            'hours' => 3.5,
            'participant_count' => 12,
            'weather' => 'Windy',
            'notes' => 'Lead debrief notes',
            'completed_by_id' => $member->getKey(),
        ]);

        $entry = app(Logbook::class)->add($member, $booking);

        $this->assertSame('3.50', (string) $entry->hours);
        $this->assertSame(12, $entry->participant_count);
        $this->assertSame('Windy', $entry->weather);
        $this->assertNull($entry->notes);
    }

    public function test_add_guesses_hours_from_booking_when_no_debrief(): void
    {
        $member = User::factory()->teamMember()->create();
        $booking = $this->pastBooking();
        $this->attend($booking, $member, BookingAttendeeStatus::Accepted);

        $entry = app(Logbook::class)->add($member, $booking);
        $state = app(Logbook::class)->addFormState($booking);

        $this->assertEqualsWithDelta(2.0, (float) $entry->hours, 0.001);
        $this->assertSame($booking->suggestedOperatingHours(), $state['hours']);
        $this->assertEqualsWithDelta(2.0, (float) $state['hours'], 0.001);
        $this->assertNull($entry->participant_count);
        $this->assertNull($entry->weather);
        $this->assertNull($entry->notes);
    }

    public function test_copy_from_debrief_leaves_personal_notes(): void
    {
        $member = User::factory()->teamMember()->create();
        $booking = $this->pastBooking();
        $this->attend($booking, $member, BookingAttendeeStatus::Accepted);

        BookingDebrief::factory()->create([
            'booking_id' => $booking->getKey(),
            'hours' => 4,
            'participant_count' => 6,
            'weather' => 'Clear',
            'notes' => 'Debrief only',
            'completed_by_id' => $member->getKey(),
        ]);

        $entry = LogbookEntry::factory()->create([
            'user_id' => $member->getKey(),
            'booking_id' => $booking->getKey(),
            'hours' => 1,
            'participant_count' => 1,
            'weather' => 'Wet',
            'notes' => 'My personal notes',
        ]);

        app(Logbook::class)->copyFromDebrief($member, $entry->fresh());

        $entry->refresh();
        $this->assertSame('4.00', (string) $entry->hours);
        $this->assertSame(6, $entry->participant_count);
        $this->assertSame('Clear', $entry->weather);
        $this->assertSame('My personal notes', $entry->notes);
    }

    public function test_ignore_removes_from_outstanding_without_changing_status(): void
    {
        $member = User::factory()->teamMember()->create();
        $booking = $this->pastBooking();
        $this->attend($booking, $member, BookingAttendeeStatus::Accepted);

        app(Logbook::class)->ignore($member, $booking);

        $attendance = $booking->fresh()->attendees()->find($member)->attendance;
        $this->assertTrue($attendance->isAccepted());
        $this->assertNotNull($attendance->logbook_ignored_at);
        $this->assertTrue(app(Logbook::class)->outstanding($member)->isEmpty());
        $this->assertTrue(app(Logbook::class)->canAdd($member, $booking->fresh()));
    }

    public function test_remove_deletes_entry_and_ignores_so_it_stays_off_outstanding(): void
    {
        $member = User::factory()->teamMember()->create();
        $team = $member->teams()->firstOrFail();
        $booking = $this->pastBooking($team);
        $this->attend($booking, $member, BookingAttendeeStatus::Accepted);

        $entry = LogbookEntry::factory()->create([
            'user_id' => $member->getKey(),
            'booking_id' => $booking->getKey(),
            'notes' => 'Remove me',
        ]);

        $this->actingAs($member);
        Filament::setCurrentPanel('member');

        Livewire::test(LogbookPage::class)
            ->assertCanSeeTableRecords([$entry])
            ->callTableAction('delete', $entry)
            ->assertNotified(__('Removed'));

        $this->assertDatabaseMissing('logbook_entries', ['id' => $entry->getKey()]);

        $attendance = $booking->fresh()->attendees()->find($member)->attendance;
        $this->assertTrue($attendance->isAccepted());
        $this->assertNotNull($attendance->logbook_ignored_at);
        $this->assertTrue(app(Logbook::class)->outstanding($member)->isEmpty());
        $this->assertTrue(app(Logbook::class)->canAdd($member, $booking->fresh()));
    }

    public function test_decline_from_candidate_updates_attendance_when_booking_accepts_invites(): void
    {
        $member = User::factory()->teamMember()->create();
        $booking = $this->futureBooking();
        $booking->forceFill([
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
        ])->save();
        $this->attend($booking, $member, BookingAttendeeStatus::Accepted);

        $this->assertTrue(app(Logbook::class)->canDecline($booking->fresh()));

        app(Logbook::class)->decline($member, $booking->fresh());

        $this->assertTrue(
            $booking->fresh()->attendees()->find($member)->attendance->isDeclined(),
        );
        $this->assertTrue(app(Logbook::class)->outstanding($member)->isEmpty());
    }

    public function test_csv_export_shape_includes_expected_headers_and_row(): void
    {
        $member = User::factory()->teamMember()->create();
        $booking = $this->pastBooking();
        $this->attend($booking, $member, BookingAttendeeStatus::Accepted);

        $entry = LogbookEntry::factory()->create([
            'user_id' => $member->getKey(),
            'booking_id' => $booking->getKey(),
            'hours' => 2.25,
            'participant_count' => 5,
            'weather' => 'Dry',
            'notes' => 'Permit evidence',
        ]);

        $logbook = app(Logbook::class);
        $this->assertSame([
            __('Date'),
            __('Time'),
            __('Hours'),
            __('Activity'),
            __('Venue'),
            __('Participants'),
            __('Weather'),
            __('Notes'),
        ], $logbook->exportHeaders());

        $row = $logbook->exportRow($entry->fresh()->load(['booking.venue', 'booking.activity', 'booking.audience']));
        $this->assertSame('2.25', $row[2]);
        $this->assertSame($booking->activityTitle(), $row[3]);
        $this->assertSame($booking->venue?->name, $row[4]);
        $this->assertSame('5', $row[5]);
        $this->assertSame('Dry', $row[6]);
        $this->assertSame('Permit evidence', $row[7]);
    }

    public function test_pdf_export_is_print_friendly(): void
    {
        $member = User::factory()->teamMember()->create();
        $booking = $this->pastBooking();
        $this->attend($booking, $member, BookingAttendeeStatus::Accepted);

        LogbookEntry::factory()->create([
            'user_id' => $member->getKey(),
            'booking_id' => $booking->getKey(),
            'hours' => 2,
            'notes' => 'PDF notes',
        ]);

        $this->actingAs($member)
            ->get(route('member.logbook.export'))
            ->assertOk()
            ->assertSee(__('Personal logbook'), false)
            ->assertSee('PDF notes', false)
            ->assertSee(__('Print / Save as PDF'), false);
    }

    public function test_saved_log_filters_narrow_table_and_pdf_export_query(): void
    {
        $member = User::factory()->teamMember()->create();
        $team = $member->teams()->firstOrFail();

        $climbing = Activity::factory()->create(['name' => 'Logbook Climbing']);
        $abseiling = Activity::factory()->create(['name' => 'Logbook Abseiling']);
        $hiking = Activity::factory()->create(['name' => 'Logbook Hiking']);
        $team->addActivity($climbing);
        $team->addActivity($abseiling);
        $team->addActivity($hiking);

        $climbingBooking = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'activity_id' => $climbing->getKey(),
            'start_at' => now()->subDays(3)->setTime(10, 0),
            'end_at' => now()->subDays(3)->setTime(12, 0),
        ]);
        $abseilingBooking = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'activity_id' => $abseiling->getKey(),
            'start_at' => now()->subDays(2)->setTime(10, 0),
            'end_at' => now()->subDays(2)->setTime(12, 0),
        ]);
        $hikingBooking = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'activity_id' => $hiking->getKey(),
            'start_at' => now()->subMonths(2)->setTime(10, 0),
            'end_at' => now()->subMonths(2)->setTime(12, 0),
        ]);

        $this->attend($climbingBooking, $member, BookingAttendeeStatus::Accepted);
        $this->attend($abseilingBooking, $member, BookingAttendeeStatus::Accepted);
        $this->attend($hikingBooking, $member, BookingAttendeeStatus::Accepted);

        $climbingEntry = LogbookEntry::factory()->create([
            'user_id' => $member->getKey(),
            'booking_id' => $climbingBooking->getKey(),
            'notes' => 'Keep climbing',
        ]);
        $abseilingEntry = LogbookEntry::factory()->create([
            'user_id' => $member->getKey(),
            'booking_id' => $abseilingBooking->getKey(),
            'notes' => 'Keep abseiling',
        ]);
        $hikingEntry = LogbookEntry::factory()->create([
            'user_id' => $member->getKey(),
            'booking_id' => $hikingBooking->getKey(),
            'notes' => 'Drop hiking',
        ]);

        $this->actingAs($member);
        Filament::setCurrentPanel('member');

        Livewire::test(LogbookPage::class)
            ->assertCanSeeTableRecords([$climbingEntry, $abseilingEntry, $hikingEntry])
            ->filterTable('activity_id', [$climbing->getKey(), $abseiling->getKey()])
            ->assertCanSeeTableRecords([$climbingEntry, $abseilingEntry])
            ->assertCanNotSeeTableRecords([$hikingEntry])
            ->filterTable('schedule', [
                'from' => now()->subDays(7)->toDateString(),
                'to' => now()->toDateString(),
            ])
            ->assertCanSeeTableRecords([$climbingEntry, $abseilingEntry])
            ->assertCanNotSeeTableRecords([$hikingEntry]);

        $this->actingAs($member)
            ->get(route('member.logbook.export', [
                'activity' => [$climbing->getKey(), $abseiling->getKey()],
                'from' => now()->subDays(7)->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Keep climbing', false)
            ->assertSee('Keep abseiling', false)
            ->assertDontSee('Drop hiking');

        $this->actingAs($member)
            ->get(route('member.logbook.export', [
                'from' => now()->toDateString(),
                'to' => now()->subDay()->toDateString(),
            ]))
            ->assertSessionHasErrors(['to']);
    }

    public function test_logbook_mail_channel_defaults_off(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(MailChannel::BookingsLogbook->default());
        $this->assertFalse(MailPreference::allows($user, MailChannel::BookingsLogbook));
    }

    public function test_pref_off_skips_logbook_nudge(): void
    {
        Mail::fake();

        $member = User::factory()->teamMember()->create();
        $booking = $this->pastBooking();
        $this->attend($booking, $member, BookingAttendeeStatus::Accepted);

        $this->assertFalse(MailPreference::allows($member, MailChannel::BookingsLogbook));

        app(CombinedDigest::class)->send($member);

        Mail::assertNothingQueued();
        $this->assertNull(
            $booking->fresh()->attendees()->find($member)->attendance->logbook_nudged_at,
        );
    }

    public function test_pref_on_focused_logbook_nudge_stamps(): void
    {
        Mail::fake();

        $member = User::factory()->teamMember()->create([
            'mail_preferences' => [
                MailChannel::BookingsLogbook->value => true,
            ],
        ]);
        $booking = $this->pastBooking();
        $this->attend($booking, $member, BookingAttendeeStatus::Accepted);

        app(CombinedDigest::class)->send($member);

        Mail::assertQueued(BookingLogbookNudgeMail::class, function (BookingLogbookNudgeMail $mail) use ($booking, $member): bool {
            return $mail->booking->is($booking) && $mail->member->is($member);
        });
        $this->assertNotNull(
            $booking->fresh()->attendees()->find($member)->attendance->logbook_nudged_at,
        );
    }

    public function test_multiple_logbook_items_use_combined_digest(): void
    {
        Mail::fake();

        $member = User::factory()->teamMember()->create([
            'mail_preferences' => [
                MailChannel::BookingsLogbook->value => true,
            ],
        ]);
        $first = $this->pastBooking();
        $second = $this->pastBooking();
        $this->attend($first, $member, BookingAttendeeStatus::Accepted);
        $this->attend($second, $member, BookingAttendeeStatus::Accepted);

        app(CombinedDigest::class)->send($member);

        Mail::assertQueued(CombinedDigestMail::class, function (CombinedDigestMail $mail) use ($first, $second): bool {
            return is_array($mail->logbookDue)
                && count($mail->logbookDue) === 2
                && in_array($first->activityTitle(), $mail->logbookDue, true)
                && in_array($second->activityTitle(), $mail->logbookDue, true);
        });
        Mail::assertNotQueued(BookingLogbookNudgeMail::class);
    }

    public function test_member_page_lists_outstanding_and_saved(): void
    {
        $member = User::factory()->teamMember()->create();
        $outstanding = $this->pastBooking();
        $saved = $this->pastBooking();
        $this->attend($outstanding, $member, BookingAttendeeStatus::Accepted);
        $this->attend($saved, $member, BookingAttendeeStatus::Accepted);

        LogbookEntry::factory()->create([
            'user_id' => $member->getKey(),
            'booking_id' => $saved->getKey(),
            'notes' => 'Saved row',
        ]);

        $this->actingAs($member);

        Livewire::test(LogbookPage::class)
            ->assertSee($outstanding->activityTitle())
            ->assertSee($saved->activityTitle())
            ->assertSee(__('Outstanding'))
            ->assertSee(__('Saved log'));
    }

    public function test_booking_view_offers_add_or_edit_logbook_for_accepted_member(): void
    {
        $member = User::factory()->teamMember()->create();
        $team = $member->teams()->firstOrFail();
        $booking = $this->pastBooking($team);
        $this->attend($booking, $member, BookingAttendeeStatus::Accepted);

        $summary = LogbookEntryAction::bookingSummarySection($booking);
        $schema = LogbookEntryAction::schema($booking);

        $this->assertSame(__('Booking'), $summary->getHeading());
        $this->assertSame(__('View booking'), $summary->getHeaderActions()[0]->getLabel());
        $this->assertNotEmpty($summary->getHeaderActions()[0]->getUrl());
        $this->assertCount(2, $schema);
        $this->assertSame(__('Booking'), $schema[0]->getHeading());
        $this->assertSame(__('Session'), $schema[1]->getHeading());
        $this->assertNotSame('—', BookingWhen::format($booking));

        $this->actingAs($member);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionVisible('logbook')
            ->assertActionHasLabel('logbook', __('Add to logbook'))
            ->callAction('logbook', data: [
                'hours' => 2,
                'notes' => 'From booking',
            ]);

        $this->assertDatabaseHas('logbook_entries', [
            'user_id' => $member->getKey(),
            'booking_id' => $booking->getKey(),
            'notes' => 'From booking',
        ]);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionVisible('logbook')
            ->assertActionHasLabel('logbook', __('Edit logbook'));
    }

    public function test_booking_view_hides_logbook_for_tentative_attendance(): void
    {
        $member = User::factory()->teamMember()->create();
        $team = $member->teams()->firstOrFail();
        $booking = $this->pastBooking($team);
        $this->attend($booking, $member, BookingAttendeeStatus::Tentative);

        $this->actingAs($member);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionHidden('logbook');
    }

    public function test_copy_from_debrief_updates_mounted_form_without_saving(): void
    {
        $member = User::factory()->teamMember()->create();
        $team = $member->teams()->firstOrFail();
        $booking = $this->pastBooking($team);
        $this->attend($booking, $member, BookingAttendeeStatus::Accepted);

        BookingDebrief::factory()->create([
            'booking_id' => $booking->getKey(),
            'hours' => 4,
            'participant_count' => 6,
            'weather' => 'Clear',
            'notes' => 'Debrief only',
            'completed_by_id' => $member->getKey(),
        ]);

        $entry = LogbookEntry::factory()->create([
            'user_id' => $member->getKey(),
            'booking_id' => $booking->getKey(),
            'hours' => 1,
            'participant_count' => 1,
            'weather' => 'Wet',
            'notes' => 'My personal notes',
        ]);

        $this->actingAs($member);
        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionVisible(['logbook', 'copyFromDebrief'])
            ->mountAction('logbook')
            ->assertSchemaStateSet([
                'hours' => 1.0,
                'participant_count' => 1,
                'weather' => 'Wet',
                'notes' => 'My personal notes',
            ])
            ->mountAction('copyFromDebrief')
            ->assertSchemaStateSet([
                'hours' => 4.0,
                'participant_count' => 6,
                'weather' => 'Clear',
                'notes' => 'My personal notes',
            ])
            ->assertNotified(__('Copied hours, participants, and weather from the debrief.'));

        $entry->refresh();
        $this->assertSame('1.00', (string) $entry->hours);
        $this->assertSame(1, $entry->participant_count);
        $this->assertSame('Wet', $entry->weather);
        $this->assertSame('My personal notes', $entry->notes);
    }

    private function pastBooking(?Team $team = null): Booking
    {
        if ($team === null) {
            $manager = User::factory()->teamManager()->create();
            $team = $manager->teams()->first();
            assert($team !== null);
        }

        return Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'start_at' => now()->subHours(4),
            'end_at' => now()->subHours(2),
        ]);
    }

    private function futureBooking(): Booking
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->teams()->first();
        assert($team !== null);

        return Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(2),
        ]);
    }

    private function attend(Booking $booking, User $user, BookingAttendeeStatus $status): void
    {
        $booking->attendees()->syncWithoutDetaching([
            $user->getKey() => [
                'status' => $status->value,
                'token' => BookingAttendance::generateToken(),
            ],
        ]);
    }
}
