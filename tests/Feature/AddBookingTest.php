<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Filament\Manage\Resources\Bookings\BookingResource;
use App\Filament\Manage\Resources\Bookings\Pages\CreateBooking;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AddBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_leader_can_create_a_booking(): void
    {
        $user = User::factory()->teamLeader()->create();
        $activity = Activity::factory()->create(['name' => 'Climbing']);
        $venue = Venue::factory()->create(['name' => 'Fox Coverts Campsite']);

        $this->actingAs($user);

        Livewire::test(CreateBooking::class)
            ->fillForm([
                'activity_id' => $activity->id,
                'venue_id' => $venue->id,
                'start_at' => '2026-10-24 09:30:00',
                'end_at' => '2026-10-24 11:30:00',
                'timezone' => 'Europe/London',
                'status' => BookingStatus::Tentative->value,
                'group_name' => '1st Anytown Scouts',
                'notes' => 'x20 Young People',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $booking = Booking::query()->first();
        $this->assertNotNull($booking);
        $this->assertSame(BookingStatus::Tentative, $booking->status);
        $this->assertSame('1st Anytown Scouts', $booking->group_name);
        $this->assertTrue($booking->activity->is($activity));
        $this->assertTrue($booking->venue->is($venue));
    }

    public function test_team_leader_can_create_activity_and_venue_from_the_booking_form(): void
    {
        $user = User::factory()->teamLeader()->create();

        $this->actingAs($user);

        Livewire::test(CreateBooking::class)
            ->callFormComponentAction('activity_id', 'createOption', [
                'name' => 'Caving',
            ])
            ->assertHasNoFormComponentActionErrors()
            ->callFormComponentAction('venue_id', 'createOption', [
                'name' => 'Peak Cavern',
            ])
            ->assertHasNoFormComponentActionErrors()
            ->assertFormSet([
                'activity_id' => Activity::query()->where('name', 'Caving')->value('id'),
                'venue_id' => Venue::query()->where('name', 'Peak Cavern')->value('id'),
            ]);

        $this->assertDatabaseHas(Activity::class, [
            'name' => 'Caving',
            'slug' => 'caving',
        ]);
        $this->assertDatabaseHas(Venue::class, [
            'name' => 'Peak Cavern',
            'slug' => 'peak-cavern',
        ]);
    }

    public function test_team_member_cannot_create_activity_or_venue_from_the_booking_form(): void
    {
        BookingResource::skipAuthorization();

        try {
            $user = User::factory()->teamMember()->create();

            $this->actingAs($user);

            Livewire::test(CreateBooking::class)
                ->assertFormComponentActionHidden('activity_id', 'createOption')
                ->assertFormComponentActionHidden('venue_id', 'createOption');
        } finally {
            BookingResource::skipAuthorization(false);
        }
    }

    public function test_team_member_cannot_create_a_booking(): void
    {
        $user = User::factory()->teamMember()->create();

        $this->actingAs($user)
            ->get('/manage/bookings/create')
            ->assertForbidden();
    }
}
