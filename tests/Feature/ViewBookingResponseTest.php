<?php

namespace Tests\Feature;

use App\Actions\RespondToBookingAction;
use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use App\Filament\Teams\Resources\Bookings\Pages\ViewBooking;
use App\Mail\BookingChangedMail;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class ViewBookingResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_respond_choices_omit_the_current_answer(): void
    {
        $member = User::factory()->teamMember()->create();
        $booking = Booking::factory()->confirmed()->create();
        $booking->attendees()->attach($member->id, [
            'status' => BookingAttendeeStatus::Accepted->value,
        ]);

        Filament::setCurrentPanel('teams');
        $this->actingAs($member);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionHidden('respondYes')
            ->assertActionVisible('respondMaybe')
            ->assertActionVisible('respondNo')
            ->assertSee(__('Can help'));

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('respondMaybe');

        $this->assertSame(
            BookingAttendeeStatus::Tentative,
            $booking->attendees()->find($member)->attendance->status,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You cannot return to awaiting response.');

        (new RespondToBookingAction($booking))($member, BookingAttendeeStatus::NeedsAction);
    }

    public function test_respond_action_follows_the_respond_gate(): void
    {
        $booking = Booking::factory()->confirmed()->create();
        $invited = User::factory()->teamMember()->create();
        $uninvited = User::factory()->teamMember()->create();
        $manager = User::factory()->teamManager()->create();
        $booking->attendees()->attach($invited->id, [
            'status' => BookingAttendeeStatus::NeedsAction->value,
        ]);

        Filament::setCurrentPanel('teams');
        $this->actingAs($invited);
        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionVisible('respondYes');

        Filament::setCurrentPanel('teams');
        $this->actingAs($uninvited);
        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionHidden('respondYes');

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);
        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionVisible('respondYes');
    }

    public function test_responding_without_an_invite_creates_a_token_for_later_emails(): void
    {
        $booking = Booking::factory()->confirmed()->create();
        $manager = User::factory()->teamManager()->create();

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);
        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('respondYes');

        $token = $booking->attendees()->find($manager)->attendance->token;

        $this->assertNotNull($token);

        $html = (new BookingChangedMail($booking->fresh(), $manager))->render();

        $this->assertStringContainsString('View response', $html);
        $this->assertStringContainsString(
            route('respond', [$booking, $manager, 'invite' => $token]),
            $html,
        );
    }

    public function test_an_accepted_lead_can_step_down_from_the_booking_page(): void
    {
        $lead = User::factory()->teamMember()->create();
        $booking = Booking::factory()->confirmed()->create([
            'lead_instructor_id' => $lead->id,
        ]);
        $booking->attendees()->attach($lead->id, [
            'status' => BookingAttendeeStatus::Accepted->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        Filament::setCurrentPanel('teams');
        $this->actingAs($lead);

        $this->assertTrue($lead->can('update', $booking->attendees()->find($lead)->attendance));

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionVisible('respondNo')
            ->callAction('respondNo');

        $booking->refresh();

        $this->assertNull($booking->lead_instructor_id);
        $this->assertSame(BookingStatus::Tentative, $booking->status);
        $this->assertSame(
            BookingAttendeeStatus::Declined,
            $booking->attendees()->find($lead)->attendance->status,
        );
    }

    public function test_a_lead_can_step_down_after_the_activity_leaves_the_team(): void
    {
        $lead = User::factory()->teamMember()->create();
        $booking = Booking::factory()->confirmed()->create([
            'lead_instructor_id' => $lead->id,
        ]);
        $booking->attendees()->attach($lead->id, [
            'status' => BookingAttendeeStatus::Accepted->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        $booking->team->removeActivity($booking->activity);

        Filament::setCurrentPanel('teams');
        $this->actingAs($lead);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('respondNo');

        $booking->refresh();

        $this->assertNull($booking->lead_instructor_id);
        $this->assertSame(BookingStatus::Tentative, $booking->status);
        $this->assertSame(
            BookingAttendeeStatus::Declined,
            $booking->attendees()->find($lead)->attendance->status,
        );
    }

    public function test_changing_a_response_keeps_the_existing_token(): void
    {
        $member = User::factory()->teamMember()->create();
        $booking = Booking::factory()->confirmed()->create();
        $token = BookingAttendance::generateToken();
        $booking->attendees()->attach($member->id, [
            'status' => BookingAttendeeStatus::Accepted->value,
            'token' => $token,
        ]);

        (new RespondToBookingAction($booking))($member, BookingAttendeeStatus::Tentative);

        $this->assertSame($token, $booking->attendees()->find($member)->attendance->token);
    }
}
