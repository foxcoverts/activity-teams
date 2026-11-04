<?php

namespace Tests\Feature;

use App\Enums\BookingAttendeeStatus;
use App\Enums\MembershipRole;
use App\Enums\PersonalDataSection;
use App\Filament\Teams\Resources\Bookings\Pages\ViewBooking as MemberViewBooking;
use App\Filament\Teams\Resources\Bookings\RelationManagers\AttendeesRelationManager as MemberAttendeesRelationManager;
use App\Livewire\BookingContactDetails;
use App\Models\Booking;
use App\Models\PersonalDataAcknowledgement;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContactAccessTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private User $manager;

    private User $lead;

    private User $attendee;

    private User $maybe;

    private User $declined;

    private User $silent;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create();
        $this->manager = $this->person('Morgan Manager', MembershipRole::Manager);
        $this->lead = $this->person('Lee Lead');
        $this->attendee = $this->person('Alex Attendee');
        $this->maybe = $this->person('Maybe Person');
        $this->declined = $this->person('Dana Declined');
        $this->silent = $this->person('Sam Silent');

        $this->booking = $this->makeBooking(
            Carbon::parse('2026-11-10 14:00', 'Europe/London'),
            Carbon::parse('2026-11-10 18:00', 'Europe/London'),
        );
        $this->attend($this->booking, $this->lead, BookingAttendeeStatus::Accepted);
        $this->attend($this->booking, $this->attendee, BookingAttendeeStatus::Accepted);
        $this->attend($this->booking, $this->maybe, BookingAttendeeStatus::Tentative);
        $this->attend($this->booking, $this->declined, BookingAttendeeStatus::Declined);
        $this->attend($this->booking, $this->silent, BookingAttendeeStatus::NeedsAction);
        $this->booking->forceFill(['lead_instructor_id' => $this->lead->getKey()])->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function person(string $name, MembershipRole $role = MembershipRole::Member): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'phone' => '07000'.random_int(100000, 999999),
            'emergency_name' => $name.' Contact',
            'emergency_phone' => '07111'.random_int(100000, 999999),
        ]);
        $this->team->grant($user, $role);

        return $user;
    }

    private function makeBooking(Carbon $start, Carbon $end, ?string $timezone = null): Booking
    {
        return Booking::factory()->confirmed()->create([
            'team_id' => $this->team->getKey(),
            'start_at' => $start,
            'end_at' => $end,
            'timezone' => $timezone ?? $start->getTimezone()->getName(),
        ]);
    }

    private function attend(Booking $booking, User $user, BookingAttendeeStatus $status): void
    {
        $booking->attendees()->attach($user->getKey(), ['status' => $status->value]);
    }

    private function can(User $viewer, string $ability, User $subject, ?Booking $booking = null): bool
    {
        $attendance = ($booking ?? $this->booking)->fresh()->attendees->find($subject->getKey())?->attendance;

        return $attendance !== null && $viewer->fresh()->can($ability, $attendance);
    }

    public function test_the_lead_and_attendees_can_reach_each_other_by_phone(): void
    {
        Carbon::setTestNow('2026-11-10 12:00:00');

        $this->assertTrue($this->can($this->lead, 'viewPhone', $this->attendee));
        $this->assertTrue($this->can($this->lead, 'viewPhone', $this->maybe));
        $this->assertTrue($this->can($this->attendee, 'viewPhone', $this->lead));
        $this->assertTrue($this->can($this->maybe, 'viewPhone', $this->lead));
    }

    public function test_attendees_cannot_see_each_others_phones(): void
    {
        Carbon::setTestNow('2026-11-10 12:00:00');

        $this->assertFalse($this->can($this->attendee, 'viewPhone', $this->maybe));
        $this->assertFalse($this->can($this->maybe, 'viewPhone', $this->attendee));
        $this->assertFalse($this->can($this->attendee, 'viewPhone', $this->attendee));
    }

    public function test_people_who_are_not_attending_are_never_shown_or_shown_to(): void
    {
        Carbon::setTestNow('2026-11-10 12:00:00');
        $admin = User::factory()->systemAdmin()->create();
        $outsider = $this->person('Olive Outsider');

        foreach (['viewPhone', 'viewEmergencyContact'] as $ability) {
            foreach ([$this->declined, $this->silent] as $notAttending) {
                $this->assertFalse($this->can($this->lead, $ability, $notAttending), "lead sees {$notAttending->name} ({$ability})");
                $this->assertFalse($this->can($notAttending, $ability, $this->lead), "{$notAttending->name} sees lead ({$ability})");
            }

            $this->assertFalse($this->can($outsider, $ability, $this->lead));
            $this->assertFalse($this->can($admin, $ability, $this->lead));
            $this->assertFalse($this->can($admin, $ability, $this->attendee));
        }
    }

    public function test_a_team_manager_who_is_attending_has_a_phone_but_no_emergency_details_for_attendees(): void
    {
        Carbon::setTestNow('2026-11-10 12:00:00');

        $this->assertFalse($this->can($this->attendee, 'viewPhone', $this->manager));

        $this->attend($this->booking, $this->manager, BookingAttendeeStatus::Accepted);

        $this->assertTrue($this->can($this->attendee, 'viewPhone', $this->manager));
        $this->assertTrue($this->can($this->lead, 'viewPhone', $this->manager));
        $this->assertFalse($this->can($this->attendee, 'viewEmergencyContact', $this->manager));
    }

    public function test_the_phone_is_open_until_the_end_of_the_last_day_in_the_booking_timezone(): void
    {
        $booking = $this->makeBooking(
            Carbon::parse('2026-11-10 14:00', 'America/New_York'),
            Carbon::parse('2026-11-10 20:00', 'America/New_York'),
        );
        $this->attend($booking, $this->lead, BookingAttendeeStatus::Accepted);
        $this->attend($booking, $this->attendee, BookingAttendeeStatus::Accepted);
        $booking->forceFill(['lead_instructor_id' => $this->lead->getKey()])->save();

        Carbon::setTestNow('2026-11-01 00:00:00');
        $this->assertTrue($this->can($this->attendee, 'viewPhone', $this->lead, $booking), 'no start window');

        // 23:59:59 on 10 Nov in New York is 04:59:59 UTC the next day.
        Carbon::setTestNow('2026-11-11 04:59:59');
        $this->assertTrue($this->can($this->attendee, 'viewPhone', $this->lead, $booking));

        Carbon::setTestNow('2026-11-11 05:00:00');
        $this->assertFalse($this->can($this->attendee, 'viewPhone', $this->lead, $booking));
    }

    public function test_emergency_details_are_open_only_on_the_booking_dates_in_the_booking_timezone(): void
    {
        $booking = $this->makeBooking(
            Carbon::parse('2026-11-10 14:00', 'America/New_York'),
            Carbon::parse('2026-11-10 20:00', 'America/New_York'),
        );
        $this->attend($booking, $this->lead, BookingAttendeeStatus::Accepted);
        $this->attend($booking, $this->attendee, BookingAttendeeStatus::Accepted);
        $booking->forceFill(['lead_instructor_id' => $this->lead->getKey()])->save();

        Carbon::setTestNow('2026-11-10 04:59:59');
        $this->assertFalse($this->can($this->attendee, 'viewEmergencyContact', $this->lead, $booking));

        Carbon::setTestNow('2026-11-10 05:00:00');
        $this->assertTrue($this->can($this->attendee, 'viewEmergencyContact', $this->lead, $booking));
        $this->assertTrue($this->can($this->lead, 'viewEmergencyContact', $this->attendee, $booking));

        Carbon::setTestNow('2026-11-11 04:59:59');
        $this->assertTrue($this->can($this->attendee, 'viewEmergencyContact', $this->lead, $booking));

        Carbon::setTestNow('2026-11-11 05:00:00');
        $this->assertFalse($this->can($this->attendee, 'viewEmergencyContact', $this->lead, $booking));
    }

    public function test_the_window_follows_clock_changes_on_the_booking_dates(): void
    {
        // London clocks go back on 25 Oct 2026: midnight that day is 23:00 UTC on the 24th.
        $booking = $this->makeBooking(
            Carbon::parse('2026-10-25 09:00', 'Europe/London'),
            Carbon::parse('2026-10-25 17:00', 'Europe/London'),
        );
        $this->attend($booking, $this->lead, BookingAttendeeStatus::Accepted);
        $this->attend($booking, $this->attendee, BookingAttendeeStatus::Accepted);
        $booking->forceFill(['lead_instructor_id' => $this->lead->getKey()])->save();

        Carbon::setTestNow('2026-10-24 22:59:59');
        $this->assertFalse($this->can($this->attendee, 'viewEmergencyContact', $this->lead, $booking));

        Carbon::setTestNow('2026-10-24 23:00:00');
        $this->assertTrue($this->can($this->attendee, 'viewEmergencyContact', $this->lead, $booking));

        Carbon::setTestNow('2026-10-25 23:59:59');
        $this->assertTrue($this->can($this->attendee, 'viewEmergencyContact', $this->lead, $booking));

        Carbon::setTestNow('2026-10-26 00:00:00');
        $this->assertFalse($this->can($this->attendee, 'viewEmergencyContact', $this->lead, $booking));
    }

    public function test_a_booking_that_crosses_midnight_has_one_window_from_its_first_to_its_last_date(): void
    {
        $booking = $this->makeBooking(
            Carbon::parse('2026-11-10 22:00', 'Europe/London'),
            Carbon::parse('2026-11-11 02:00', 'Europe/London'),
        );
        $this->attend($booking, $this->lead, BookingAttendeeStatus::Accepted);
        $this->attend($booking, $this->attendee, BookingAttendeeStatus::Accepted);
        $booking->forceFill(['lead_instructor_id' => $this->lead->getKey()])->save();

        Carbon::setTestNow('2026-11-10 00:00:00');
        $this->assertTrue($this->can($this->attendee, 'viewEmergencyContact', $this->lead, $booking));

        Carbon::setTestNow('2026-11-11 23:59:59');
        $this->assertTrue($this->can($this->attendee, 'viewEmergencyContact', $this->lead, $booking));

        Carbon::setTestNow('2026-11-12 00:00:00');
        $this->assertFalse($this->can($this->attendee, 'viewEmergencyContact', $this->lead, $booking));
    }

    public function test_nothing_is_shown_for_a_cancelled_booking_or_to_or_from_a_suspended_person(): void
    {
        Carbon::setTestNow('2026-11-10 12:00:00');

        foreach (['viewPhone', 'viewEmergencyContact'] as $ability) {
            $this->assertTrue($this->can($this->attendee, $ability, $this->lead));
        }

        $this->attendee->forceFill(['suspended' => true])->save();

        foreach (['viewPhone', 'viewEmergencyContact'] as $ability) {
            $this->assertFalse($this->can($this->attendee, $ability, $this->lead));
            $this->assertFalse($this->can($this->lead, $ability, $this->attendee));
        }

        $this->attendee->forceFill(['suspended' => false])->save();
        $this->booking->forceFill(['status' => 'cancelled'])->save();

        foreach (['viewPhone', 'viewEmergencyContact'] as $ability) {
            $this->assertFalse($this->can($this->attendee, $ability, $this->lead));
        }
    }

    public function test_the_viewer_must_acknowledge_the_notice_before_details_are_shown(): void
    {
        Carbon::setTestNow('2026-11-10 12:00:00');
        $this->actingAs($this->attendee);

        Livewire::test(BookingContactDetails::class, ['booking' => $this->booking, 'person' => $this->lead, 'kind' => 'phone'])
            ->assertSee('legitimate team matters')
            ->assertDontSee($this->lead->phone)
            ->call('acknowledge')
            ->assertSeeHtml('href="tel:'.$this->lead->phone.'"')
            ->assertDontSee($this->lead->emergency_phone);

        $this->assertTrue(PersonalDataAcknowledgement::query()
            ->where('viewer_id', $this->attendee->getKey())
            ->where('subject_id', $this->lead->getKey())
            ->where('section', PersonalDataSection::ContactDetails)
            ->exists());

        Livewire::test(BookingContactDetails::class, ['booking' => $this->booking, 'person' => $this->lead, 'kind' => 'emergency'])
            ->assertDontSee($this->lead->emergency_phone)
            ->call('acknowledge')
            ->assertSee('Emergency contact name')
            ->assertSee($this->lead->emergency_name)
            ->assertSee('Emergency contact phone')
            ->assertSeeHtml('href="tel:'.$this->lead->emergency_phone.'"');
    }

    public function test_the_details_component_refuses_people_who_may_not_view_them(): void
    {
        Carbon::setTestNow('2026-11-10 12:00:00');
        $this->actingAs($this->declined);

        Livewire::test(BookingContactDetails::class, ['booking' => $this->booking, 'person' => $this->lead, 'kind' => 'phone'])
            ->assertForbidden();

        $this->actingAs($this->attendee);

        Livewire::test(BookingContactDetails::class, ['booking' => $this->booking, 'person' => $this->maybe, 'kind' => 'phone'])
            ->assertForbidden();
    }

    public function test_the_guest_list_offers_contact_details_only_where_they_may_be_viewed(): void
    {
        Carbon::setTestNow('2026-11-10 12:00:00');
        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($this->attendee);

        Filament::setCurrentPanel(Filament::getPanel('teams'));
        Filament::setTenant($this->booking->team);
        Livewire::test(MemberAttendeesRelationManager::class, ['ownerRecord' => $this->booking, 'pageClass' => MemberViewBooking::class])
            ->assertSee('Lee Lead')
            ->assertSee('Emergency')
            ->assertDontSee($this->lead->phone)
            ->mountTableAction('contactPhone', $this->lead)
            ->assertMountedActionModalSee('legitimate team matters');

        Filament::setCurrentPanel(Filament::getPanel('teams'));
        Filament::setTenant($this->booking->team);
        Livewire::test(MemberAttendeesRelationManager::class, ['ownerRecord' => $this->booking, 'pageClass' => MemberViewBooking::class])
            ->mountTableAction('contactPhone', $this->maybe)
            ->assertTableActionNotMounted('contactPhone');

        Filament::setCurrentPanel(Filament::getPanel('teams'));
        Filament::setTenant($this->booking->team);
        Livewire::test(MemberAttendeesRelationManager::class, ['ownerRecord' => $this->booking, 'pageClass' => MemberViewBooking::class])
            ->mountTableAction('emergencyContact', $this->maybe)
            ->assertTableActionNotMounted('emergencyContact');
    }

    public function test_no_button_is_offered_for_a_detail_the_person_has_not_shared(): void
    {
        Carbon::setTestNow('2026-11-10 12:00:00');
        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->lead->forceFill(['phone' => null, 'emergency_phone' => null])->save();
        $this->actingAs($this->attendee);

        $page = fn (): array => ['ownerRecord' => $this->booking->fresh(), 'pageClass' => MemberViewBooking::class];

        Livewire::test(MemberAttendeesRelationManager::class, $page())
            ->assertDontSee('contactPhone')
            ->assertDontSee('emergencyContact')
            ->mountTableAction('contactPhone', $this->lead)
            ->assertTableActionNotMounted('contactPhone');

        $this->lead->forceFill(['phone' => '07000111222'])->save();

        Livewire::test(MemberAttendeesRelationManager::class, $page())
            ->assertSee('contactPhone')
            ->assertDontSee('emergencyContact');
    }
}
