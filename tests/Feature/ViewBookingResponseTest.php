<?php

namespace Tests\Feature;

use App\Actions\RespondToBookingAction;
use App\Enums\BookingAttendeeResponse;
use App\Enums\BookingAttendeeStatus;
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

    public function test_respond_action_opens_on_the_current_answer(): void
    {
        $member = User::factory()->teamMember()->create();
        $booking = Booking::factory()->confirmed()->create();
        $booking->attendees()->attach($member->id, [
            'status' => BookingAttendeeStatus::Accepted->value,
        ]);

        Filament::setCurrentPanel('teams');
        $this->actingAs($member);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->mountAction('respond')
            ->assertActionDataSet([
                'response' => BookingAttendeeResponse::Yes,
            ])
            ->assertMountedActionModalDontSee('Awaiting response');

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('respond', data: [
                'response' => BookingAttendeeStatus::NeedsAction->value,
            ]);

        $this->assertSame(
            BookingAttendeeStatus::Accepted,
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
            ->assertActionVisible('respond');

        Filament::setCurrentPanel('teams');
        $this->actingAs($uninvited);
        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionHidden('respond');

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);
        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionVisible('respond');
    }

    public function test_responding_without_an_invite_creates_a_token_for_later_emails(): void
    {
        $booking = Booking::factory()->confirmed()->create();
        $manager = User::factory()->teamManager()->create();

        Filament::setCurrentPanel('teams');
        $this->actingAs($manager);
        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('respond', data: [
                'response' => BookingAttendeeResponse::Yes->value,
            ]);

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
        $booking = Booking::factory()->create([
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
            ->assertActionVisible('respond')
            ->callAction('respond', data: [
                'response' => BookingAttendeeResponse::No->value,
            ]);

        $booking->refresh();

        $this->assertNull($booking->lead_instructor_id);
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
