<?php

namespace Tests\Feature;

use App\Actions\InviteAttendeesAction;
use App\Filament\Manage\Resources\Bookings\Pages\EditBooking;
use App\Filament\Manage\Resources\Bookings\RelationManagers\AttendeesRelationManager;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InviteAttendeesTest extends TestCase
{
    use RefreshDatabase;

    public function test_upcoming_bookings_can_be_invited_to(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $booking = Booking::factory()->create();

        $this->actingAs($leader);

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => EditBooking::class,
        ])->assertTableActionVisible('invite');
    }

    public function test_past_bookings_cannot_be_invited_to(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $member = User::factory()->teamMember()->create();
        $booking = Booking::factory()->create([
            'start_at' => now()->subDays(2)->setTime(9, 30),
            'end_at' => now()->subDays(2)->setTime(11, 30),
        ]);

        $this->actingAs($leader);

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => EditBooking::class,
        ])->assertTableActionHidden('invite');

        app(InviteAttendeesAction::class)($booking, [$member->id], $leader);

        $this->assertDatabaseMissing('booking_user', [
            'booking_id' => $booking->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_cancelled_bookings_cannot_be_invited_to(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $member = User::factory()->teamMember()->create();
        $booking = Booking::factory()->cancelled()->create();

        $this->actingAs($leader);

        Livewire::test(AttendeesRelationManager::class, [
            'ownerRecord' => $booking,
            'pageClass' => EditBooking::class,
        ])->assertTableActionHidden('invite');

        app(InviteAttendeesAction::class)($booking, [$member->id], $leader);

        $this->assertDatabaseMissing('booking_user', [
            'booking_id' => $booking->id,
            'user_id' => $member->id,
        ]);
    }
}
