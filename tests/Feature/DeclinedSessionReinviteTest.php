<?php

namespace Tests\Feature;

use App\Actions\InviteAttendeesAction;
use App\Actions\OverrideAttendanceAction;
use App\Actions\RespondToBookingAction;
use App\Actions\UpdateBookingStatusAction;
use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use App\Livewire\InviteAttendees;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use App\Models\Venue;
use App\Support\DeclinedSessions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeclinedSessionReinviteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('teams');
        $this->travelTo('2026-11-01 12:00:00');
    }

    public function test_member_decline_stores_the_current_session(): void
    {
        [$booking, $member] = $this->bookingWithInvite();

        (new RespondToBookingAction($booking, $member))($member, BookingAttendeeStatus::Declined);

        $attendance = $this->attendance($booking, $member);

        $this->assertTrue($attendance->sessionWasDeclined($booking));
        $this->assertFalse($attendance->isEligibleForReinvite($booking));
        $this->assertSame(
            DeclinedSessions::fromBooking($booking)->toArray(),
            DeclinedSessions::from($attendance->declined_sessions)->toArray(),
        );
    }

    public function test_manager_and_duplicate_declines_keep_a_single_session_entry(): void
    {
        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->teamMember()->create();
        $booking = Booking::factory()->create();
        $booking->attendees()->attach($member->getKey(), [
            'status' => BookingAttendeeStatus::NeedsAction->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        app(OverrideAttendanceAction::class)($booking, $member, $manager, BookingAttendeeStatus::Declined);
        app(OverrideAttendanceAction::class)(
            $booking,
            $member,
            $manager,
            BookingAttendeeStatus::Declined,
            'Still no',
        );

        $this->assertCount(1, DeclinedSessions::from($this->attendance($booking, $member)->declined_sessions)->toArray());
    }

    public function test_unchanged_session_keeps_declined_person_out_of_the_queue(): void
    {
        [$booking, $member, $manager] = $this->declinedOnCurrentSession();

        $this->actingAs($manager);

        Livewire::test(InviteAttendees::class, ['booking' => $booking->fresh()])
            ->assertSet('queued', [])
            ->call('add', $member->getKey())
            ->assertSet('queued', []);
    }

    public function test_changed_session_allows_manual_reinvite_without_auto_queue(): void
    {
        [$booking, $member, $manager] = $this->declinedOnCurrentSession();
        $original = DeclinedSessions::fromBooking($booking)->toArray();

        $booking->update([
            'start_at' => $booking->start_at->copy()->addHours(2),
            'end_at' => $booking->end_at->copy()->addHours(2),
        ]);

        $this->assertTrue($this->attendance($booking, $member)->isEligibleForReinvite($booking->fresh()));

        $this->actingAs($manager);

        Livewire::test(InviteAttendees::class, ['booking' => $booking->fresh()])
            ->assertSet('queued', [])
            ->assertSee($member->name)
            ->assertSee('Cannot make it')
            ->call('add', $member->getKey())
            ->assertSet('queued', [$member->getKey()])
            ->call('send')
            ->assertHasNoErrors();

        $attendance = $this->attendance($booking, $member);
        $this->assertSame(BookingAttendeeStatus::NeedsAction, $attendance->status);
        $this->assertSame($original, DeclinedSessions::from($attendance->declined_sessions)->toArray());
    }

    public function test_add_shown_can_batch_reinvite_eligible_declines(): void
    {
        [$booking, $member, $manager] = $this->declinedOnCurrentSession();
        $booking->attendees()->attach($manager->getKey(), [
            'status' => BookingAttendeeStatus::Accepted->value,
            'token' => BookingAttendance::generateToken(),
        ]);
        $booking->update([
            'start_at' => $booking->start_at->copy()->addDay(),
            'end_at' => $booking->end_at->copy()->addDay(),
        ]);

        $this->actingAs($manager);

        Livewire::test(InviteAttendees::class, ['booking' => $booking->fresh()])
            ->assertSet('queued', [])
            ->call('addShown')
            ->assertSet('queued', [$member->getKey()]);
    }

    public function test_declining_again_retains_both_sessions_and_blocks_returning_to_either(): void
    {
        [$booking, $member, $manager] = $this->declinedOnCurrentSession();
        $first = [
            'start_at' => $booking->start_at->copy(),
            'end_at' => $booking->end_at->copy(),
            'venue_id' => $booking->venue_id,
        ];

        $booking->update([
            'start_at' => $booking->start_at->copy()->addHours(3),
            'end_at' => $booking->end_at->copy()->addHours(3),
        ]);

        app(InviteAttendeesAction::class)($booking->fresh(), [$member->getKey()], $manager);
        (new RespondToBookingAction($booking->fresh(), $member))($member, BookingAttendeeStatus::Declined);

        $attendance = $this->attendance($booking, $member);
        $this->assertCount(2, DeclinedSessions::from($attendance->declined_sessions)->toArray());
        $this->assertTrue($attendance->sessionWasDeclined($booking->fresh()));

        $booking->update([
            'start_at' => $first['start_at'],
            'end_at' => $first['end_at'],
            'venue_id' => $first['venue_id'],
        ]);

        $this->assertFalse($this->attendance($booking, $member)->isEligibleForReinvite($booking->fresh()));
    }

    public function test_accept_after_reinvite_keeps_declined_session_history(): void
    {
        [$booking, $member, $manager] = $this->declinedOnCurrentSession();
        $history = DeclinedSessions::from($this->attendance($booking, $member)->declined_sessions)->toArray();

        $booking->update([
            'venue_id' => Venue::factory()->create()->getKey(),
        ]);

        app(InviteAttendeesAction::class)($booking->fresh(), [$member->getKey()], $manager);
        (new RespondToBookingAction($booking->fresh(), $member))($member, BookingAttendeeStatus::Accepted);

        $attendance = $this->attendance($booking, $member);
        $this->assertSame(BookingAttendeeStatus::Accepted, $attendance->status);
        $this->assertSame($history, DeclinedSessions::from($attendance->declined_sessions)->toArray());
    }

    public function test_restore_keeps_matching_decline_and_blocks_reinvite(): void
    {
        [$booking, $member, $manager] = $this->declinedOnCurrentSession();

        $booking->update(['status' => BookingStatus::Cancelled]);
        app(UpdateBookingStatusAction::class)->restore($booking->fresh(), $manager);

        $attendance = $this->attendance($booking, $member);
        $this->assertSame(BookingAttendeeStatus::Declined, $attendance->status);
        $this->assertFalse($attendance->isEligibleForReinvite($booking->fresh()));
    }

    public function test_removing_an_attendee_drops_declined_session_history(): void
    {
        [$booking, $member] = $this->declinedOnCurrentSession();

        $booking->attendees()->detach($member->getKey());

        $this->assertDatabaseMissing('booking_user', [
            'booking_id' => $booking->getKey(),
            'user_id' => $member->getKey(),
        ]);
    }

    public function test_venue_only_change_makes_decline_reinvitable(): void
    {
        [$booking, $member] = $this->declinedOnCurrentSession();

        $booking->update([
            'venue_id' => Venue::factory()->create()->getKey(),
        ]);

        $this->assertTrue($this->attendance($booking, $member)->isEligibleForReinvite($booking->fresh()));
    }

    /**
     * @return array{0: Booking, 1: User}
     */
    private function bookingWithInvite(): array
    {
        $member = User::factory()->teamMember()->create();
        $booking = Booking::factory()->create([
            'start_at' => '2026-11-10 10:00:00',
            'end_at' => '2026-11-10 12:00:00',
            'venue_id' => Venue::factory()->create()->getKey(),
        ]);
        $booking->attendees()->attach($member->getKey(), [
            'status' => BookingAttendeeStatus::NeedsAction->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        return [$booking, $member];
    }

    /**
     * @return array{0: Booking, 1: User, 2: User}
     */
    private function declinedOnCurrentSession(): array
    {
        $manager = User::factory()->teamManager()->create();
        [$booking, $member] = $this->bookingWithInvite();
        (new RespondToBookingAction($booking, $member))($member, BookingAttendeeStatus::Declined);

        return [$booking->fresh(), $member, $manager];
    }

    private function attendance(Booking $booking, User $member): BookingAttendance
    {
        return $booking->fresh()->attendees()->findOrFail($member->getKey())->attendance;
    }
}
