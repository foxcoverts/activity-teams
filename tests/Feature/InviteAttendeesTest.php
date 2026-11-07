<?php

namespace Tests\Feature;

use App\Actions\InviteAttendeesAction;
use App\Enums\BookingAttendeeStatus;
use App\Filament\Teams\Resources\Bookings\Pages\EditBooking;
use App\Filament\Teams\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Teams\Resources\Bookings\RelationManagers\AttendeesRelationManager;
use App\Mail\BookingInviteMail;
use App\Models\Booking;
use App\Models\BookingAttendance;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class InviteAttendeesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('teams');
    }

    public function test_upcoming_bookings_can_be_invited_to(): void
    {
        $manager = User::factory()->teamManager()->create();
        $booking = Booking::factory()->create();

        $this->actingAs($manager);

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => EditBooking::class,
        ])->assertTableActionVisible('invite');
    }

    public function test_invite_email_links_to_the_respond_page(): void
    {
        Mail::fake();

        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->teamMember()->create();
        $booking = Booking::factory()->create();

        app(InviteAttendeesAction::class)($booking, [$member->id], $manager);

        $token = $booking->attendees()->find($member)?->attendance->token;

        Mail::assertSent(BookingInviteMail::class, function (BookingInviteMail $mail) use ($booking, $member, $token): bool {
            return str_contains(
                $mail->render(),
                route('respond', [$booking, $member, 'invite' => $token]),
            );
        });
    }

    public function test_manager_can_remove_an_attendee_from_the_booking(): void
    {
        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->teamMember()->create();
        $booking = Booking::factory()->create();
        $booking->attendees()->attach($member->id, [
            'status' => BookingAttendeeStatus::NeedsAction->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        $this->actingAs($manager);

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => ViewBooking::class,
        ])
            ->assertTableActionVisible('detach', $member)
            ->assertTableActionHasLabel('detach', 'Remove', $member);

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => EditBooking::class,
        ])
            ->callTableAction('detach', $member)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('booking_user', [
            'booking_id' => $booking->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_accepted_lead_instructor_cannot_be_removed(): void
    {
        $manager = User::factory()->teamManager()->create();
        $booking = Booking::factory()->create([
            'lead_instructor_id' => $manager->id,
        ]);
        $booking->attendees()->attach($manager->id, [
            'status' => BookingAttendeeStatus::Accepted->value,
            'token' => BookingAttendance::generateToken(),
        ]);

        $this->actingAs($manager);

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => EditBooking::class,
        ])->assertTableActionHidden('detach', $manager);
    }

    public function test_past_bookings_cannot_be_invited_to(): void
    {
        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->teamMember()->create();
        $booking = Booking::factory()->create([
            'start_at' => now()->subDays(2)->setTime(9, 30),
            'end_at' => now()->subDays(2)->setTime(11, 30),
        ]);

        $this->actingAs($manager);

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => EditBooking::class,
        ])->assertTableActionHidden('invite');

        app(InviteAttendeesAction::class)($booking, [$member->id], $manager);

        $this->assertDatabaseMissing('booking_user', [
            'booking_id' => $booking->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_cancelled_bookings_cannot_be_invited_to(): void
    {
        $manager = User::factory()->teamManager()->create();
        $member = User::factory()->teamMember()->create();
        $booking = Booking::factory()->cancelled()->create();

        $this->actingAs($manager);

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => EditBooking::class,
        ])->assertTableActionHidden('invite');

        app(InviteAttendeesAction::class)($booking, [$member->id], $manager);

        $this->assertDatabaseMissing('booking_user', [
            'booking_id' => $booking->id,
            'user_id' => $member->id,
        ]);
    }
}
