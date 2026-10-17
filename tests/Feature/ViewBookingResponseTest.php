<?php

namespace Tests\Feature;

use App\Actions\RespondToBookingAction;
use App\Enums\Accreditation;
use App\Enums\BookingAttendeeStatus;
use App\Filament\Team\Resources\Bookings\Pages\ViewBooking;
use App\Models\Booking;
use App\Models\User;
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

        $this->actingAs($member);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->mountAction('respond')
            ->assertActionDataSet([
                'status' => BookingAttendeeStatus::Accepted->value,
            ])
            ->assertMountedActionModalDontSee('Awaiting response');

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->callAction('respond', data: [
                'status' => BookingAttendeeStatus::NeedsAction->value,
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
        $manager = User::factory()->teamMember()->create([
            'accreditations' => [Accreditation::ManageBookings],
        ]);
        $booking->attendees()->attach($invited->id, [
            'status' => BookingAttendeeStatus::NeedsAction->value,
        ]);

        $this->actingAs($invited);
        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionVisible('respond');

        $this->actingAs($uninvited);
        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionHidden('respond');

        $this->actingAs($manager);
        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertActionVisible('respond');
    }
}
