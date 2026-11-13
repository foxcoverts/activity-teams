<?php

namespace Tests\Feature;

use App\Actions\CompleteDebriefAction;
use App\Actions\ConfirmAttendanceAction;
use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use App\Enums\TeamFeature;
use App\Filament\Teams\Resources\Bookings\Pages\ViewBooking;
use App\Mail\BookingDebriefNudgeMail;
use App\Mail\CombinedDigestMail;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\BookingDebrief;
use App\Models\EquipmentSet;
use App\Models\Team;
use App\Models\User;
use App\Support\CombinedDigest;
use App\Support\DebriefCatchUp;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class BookingDebriefTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('teams');
    }

    public function test_complete_debrief_records_fields_marks_and_kit(): void
    {
        $lead = User::factory()->teamMember()->create();
        $member = User::factory()->teamMember()->create();
        $booking = $this->pastBooking(lead: $lead, debrief: true);
        $set = EquipmentSet::factory()->forTeam($booking->team)->create(['name' => 'Ropes']);
        $set->activities()->attach($booking->activity_id);
        $this->attend($booking, $member, BookingAttendeeStatus::Accepted);

        $debrief = app(CompleteDebriefAction::class)($booking, $lead, [
            'hours' => 2.5,
            'participant_count' => 8,
            'weather' => 'Dry',
            'notes' => 'Good session',
            'equipment_set_ids' => [(string) $set->getKey()],
            'marks' => [
                (string) $member->getKey() => ConfirmAttendanceAction::MARK_CONFIRM,
            ],
        ]);

        $this->assertInstanceOf(BookingDebrief::class, $debrief);
        $this->assertSame('2.50', (string) $debrief->hours);
        $this->assertSame(8, $debrief->participant_count);
        $this->assertSame('Dry', $debrief->weather);
        $this->assertSame('Good session', $debrief->notes);
        $this->assertTrue($debrief->equipmentSets->contains($set));
        $this->assertTrue($booking->fresh()->attendees()->find($member)->attendance->isAttendanceConfirmed());
        $this->assertFalse($booking->fresh()->needsDebrief());
    }

    public function test_complete_debrief_confirms_tentative_booking_with_lead_when_feature_on(): void
    {
        $lead = User::factory()->teamMember()->create();
        $member = User::factory()->teamMember()->create();
        $booking = $this->pastBooking(lead: $lead, debrief: true, confirmed: false);
        $this->attend($booking, $member, BookingAttendeeStatus::Accepted);
        $this->attend($booking, $lead, BookingAttendeeStatus::Accepted);

        app(CompleteDebriefAction::class)($booking, $lead, [
            'marks' => [
                (string) $member->getKey() => ConfirmAttendanceAction::MARK_CONFIRM,
                (string) $lead->getKey() => ConfirmAttendanceAction::MARK_CONFIRM,
            ],
        ]);

        $this->assertTrue($booking->fresh()->isConfirmed());
    }

    public function test_confirm_attendees_also_confirms_tentative_when_debrief_enabled(): void
    {
        $lead = User::factory()->teamMember()->create();
        $member = User::factory()->teamMember()->create();
        $booking = $this->pastBooking(lead: $lead, debrief: true, confirmed: false);
        $this->attend($booking, $member, BookingAttendeeStatus::Accepted);

        app(ConfirmAttendanceAction::class)($booking, $lead, [
            (string) $member->getKey() => ConfirmAttendanceAction::MARK_CONFIRM,
        ]);

        $this->assertTrue($booking->fresh()->isConfirmed());
    }

    public function test_complete_debrief_action_is_hidden_without_feature(): void
    {
        $manager = User::factory()->teamManager()->create();
        $booking = $this->pastBooking(debrief: false);

        $this->actingAs($manager);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionHidden('completeDebrief');
    }

    public function test_confirm_attendees_is_hidden_while_complete_debrief_is_available(): void
    {
        $lead = User::factory()->teamMember()->create();
        $booking = $this->pastBooking(lead: $lead, debrief: true);

        $this->actingAs($lead);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionVisible('completeDebrief')
            ->assertActionHidden('confirmAttendees');
    }

    public function test_confirm_attendees_returns_after_debrief_is_completed(): void
    {
        $lead = User::factory()->teamMember()->create();
        $booking = $this->pastBooking(lead: $lead, debrief: true);
        BookingDebrief::factory()->create([
            'booking_id' => $booking->getKey(),
            'completed_by_id' => $lead->getKey(),
        ]);

        $this->actingAs($lead);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionHidden('completeDebrief')
            ->assertActionVisible('confirmAttendees');
    }

    public function test_lead_can_complete_debrief_from_view_booking(): void
    {
        $lead = User::factory()->teamMember()->create();
        $member = User::factory()->teamMember()->create();
        $booking = $this->pastBooking(lead: $lead, debrief: true);
        $this->attend($booking, $member, BookingAttendeeStatus::Accepted);

        $this->actingAs($lead);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionVisible('completeDebrief')
            ->assertActionHidden('confirmAttendees')
            ->callAction('completeDebrief', data: [
                'hours' => 3,
                'participant_count' => 4,
                'weather' => 'Clear',
                'notes' => null,
                'equipment_set_ids' => [],
                'needs_confirming' => [[
                    'user_id' => (string) $member->getKey(),
                    'name' => $member->name,
                    'mark' => ConfirmAttendanceAction::MARK_CONFIRM,
                ]],
                'confirmed' => [],
            ]);

        $this->assertNotNull($booking->fresh()->debrief);
        $this->assertSame(4, $booking->fresh()->debrief->participant_count);
    }

    public function test_focused_debrief_nudge_sends_after_grace_period(): void
    {
        Mail::fake();

        $lead = User::factory()->teamMember()->create();
        $booking = $this->pastBooking(lead: $lead, debrief: true, endedHoursAgo: 2);

        app(CombinedDigest::class)->send($lead);

        Mail::assertQueued(BookingDebriefNudgeMail::class, function (BookingDebriefNudgeMail $mail) use ($booking, $lead): bool {
            return $mail->booking->is($booking)
                && $mail->lead->is($lead)
                && $mail->week === false;
        });
        $this->assertNotNull($booking->fresh()->debrief_nudged_at);
    }

    public function test_week_debrief_nudge_follows_initial_stamp(): void
    {
        Mail::fake();

        $lead = User::factory()->teamMember()->create();
        $booking = $this->pastBooking(lead: $lead, debrief: true, endedHoursAgo: 24 * 8);
        $booking->forceFill(['debrief_nudged_at' => now()->subWeek()])->save();

        app(CombinedDigest::class)->send($lead);

        Mail::assertQueued(BookingDebriefNudgeMail::class, function (BookingDebriefNudgeMail $mail): bool {
            return $mail->week === true;
        });
        $this->assertNotNull($booking->fresh()->debrief_week_nudged_at);
    }

    public function test_debrief_summary_appears_in_combined_digest_when_other_content_sends(): void
    {
        Mail::fake();

        $lead = User::factory()->teamMember()->create();
        $booking = $this->pastBooking(lead: $lead, debrief: true, endedHoursAgo: 2);
        $other = $this->pastBooking(lead: $lead, debrief: true, endedHoursAgo: 3);

        app(CombinedDigest::class)->send($lead);

        Mail::assertQueued(CombinedDigestMail::class, function (CombinedDigestMail $mail) use ($booking, $other): bool {
            return is_array($mail->debriefsDue)
                && count($mail->debriefsDue) === 2
                && in_array($booking->activityTitle(), $mail->debriefsDue, true)
                && in_array($other->activityTitle(), $mail->debriefsDue, true);
        });
        Mail::assertNotQueued(BookingDebriefNudgeMail::class);
    }

    public function test_cannot_debrief_twice(): void
    {
        $lead = User::factory()->teamMember()->create();
        $member = User::factory()->teamMember()->create();
        $booking = $this->pastBooking(lead: $lead, debrief: true);
        $this->attend($booking, $member, BookingAttendeeStatus::Accepted);

        app(CompleteDebriefAction::class)($booking, $lead, [
            'marks' => [
                (string) $member->getKey() => ConfirmAttendanceAction::MARK_CONFIRM,
            ],
        ]);

        $this->expectException(ValidationException::class);

        app(CompleteDebriefAction::class)($booking->fresh(), $lead, [
            'marks' => [
                (string) $member->getKey() => ConfirmAttendanceAction::MARK_CONFIRM,
            ],
        ]);
    }

    public function test_pre_activation_bookings_are_not_outstanding_but_can_still_be_debriefed(): void
    {
        $lead = User::factory()->teamMember()->create();
        $member = User::factory()->teamMember()->create();
        $manager = User::factory()->teamManager()->create();
        $team = $manager->teams()->first();
        assert($team !== null);

        $booking = Booking::factory()->confirmed()->create([
            'team_id' => $team->getKey(),
            'start_at' => now()->subDays(10)->setTime(9, 0),
            'end_at' => now()->subDays(10)->setTime(11, 0),
            'lead_instructor_id' => $lead->getKey(),
        ]);
        $this->attend($booking, $member, BookingAttendeeStatus::Accepted);

        $team->enableFeature(TeamFeature::Debrief, now()->subDay());
        $team->save();

        $this->assertFalse($booking->fresh()->needsDebrief());

        $this->actingAs($lead);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionVisible('completeDebrief')
            ->assertActionHidden('confirmAttendees');

        $this->assertTrue(
            app(DebriefCatchUp::class)->outstandingLedBy($lead)->isEmpty(),
        );
    }

    public function test_enabling_debrief_again_refreshes_activated_at(): void
    {
        $team = Team::factory()->create();
        $first = now()->subMonths(3);

        $team->enableFeature(TeamFeature::Debrief, $first);
        $team->save();

        $this->assertTrue($team->featureEnabled(TeamFeature::Debrief));
        $this->assertSame(
            $first->toIso8601String(),
            $team->fresh()->features[TeamFeature::Debrief->value]['activated_at'] ?? null,
        );

        $team->disableFeature(TeamFeature::Debrief);
        $team->save();
        $this->assertFalse($team->fresh()->featureEnabled(TeamFeature::Debrief));

        $this->travel(1)->day();
        $team = $team->fresh();
        $team->enableFeature(TeamFeature::Debrief);
        $team->save();

        $activatedAt = $team->fresh()->featureActivatedAt(TeamFeature::Debrief);
        $this->assertNotNull($activatedAt);
        $this->assertTrue($activatedAt->greaterThan($first));
    }

    private function pastBooking(
        ?User $lead = null,
        bool $debrief = true,
        bool $confirmed = true,
        int $endedHoursAgo = 2,
    ): Booking {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->teams()->first();
        assert($team !== null);

        if ($debrief) {
            $team->enableFeature(TeamFeature::Debrief, now()->subMonths(2));
            $team->save();
        }

        $factory = $confirmed
            ? Booking::factory()->confirmed()
            : Booking::factory()->state(['status' => BookingStatus::Tentative]);

        return $factory->create([
            'team_id' => $team->getKey(),
            'start_at' => now()->subHours($endedHoursAgo + 2),
            'end_at' => now()->subHours($endedHoursAgo),
            'lead_instructor_id' => $lead?->getKey(),
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
