<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Filament\Manage\Resources\Bookings\BookingResource;
use App\Filament\Manage\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Manage\Resources\Bookings\Pages\EditBooking;
use App\Models\Activity;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_create_booking_defaults_to_saturday_morning_in_the_users_timezone(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 12:00:00', 'UTC'));

        $user = User::factory()->teamLeader()->create([
            'timezone' => 'America/New_York',
        ]);
        $activity = Activity::factory()->create();
        $venue = Venue::factory()->create();

        $this->actingAs($user);

        Livewire::test(CreateBooking::class)
            ->assertFormSet([
                'timezone' => 'America/New_York',
                'start_at' => '2026-10-17 13:30:00',
                'end_at' => '2026-10-17 15:30:00',
            ])
            ->fillForm([
                'activity_id' => $activity->id,
                'venue_id' => $venue->id,
                'group_name' => '1st Anytown Scouts',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $booking = Booking::query()->first();
        $this->assertNotNull($booking);
        $this->assertSame('2026-10-17 13:30:00', $booking->start_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-17 15:30:00', $booking->end_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('America/New_York', $booking->timezone->getName());
    }

    public function test_changing_the_start_keeps_the_booking_duration(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 12:00:00', 'UTC'));

        $user = User::factory()->teamLeader()->create([
            'timezone' => 'America/New_York',
        ]);

        $this->actingAs($user);

        Livewire::test(CreateBooking::class)
            ->set('data.start_at', '2026-10-18 10:00:00')
            ->assertFormSet([
                'start_at' => '2026-10-18 14:00:00',
                'end_at' => '2026-10-18 16:00:00',
            ])
            ->set('data.end_at', '2026-10-18 14:00:00')
            ->assertFormSet([
                'end_at' => '2026-10-18 18:00:00',
            ])
            ->set('data.start_at', '2026-10-18 09:00:00')
            ->assertFormSet([
                'end_at' => '2026-10-18 17:00:00',
            ])
            ->set('data.end_at', '2026-10-18 08:00:00')
            ->assertFormSet([
                'end_at' => '2026-10-18 13:00:00',
            ]);
    }

    public function test_editing_a_booking_keeps_its_existing_duration(): void
    {
        $user = User::factory()->teamLeader()->create([
            'timezone' => 'America/New_York',
        ]);
        $booking = Booking::factory()->create([
            'start_at' => Carbon::parse('2026-10-20 13:00:00', 'UTC'),
            'end_at' => Carbon::parse('2026-10-20 16:00:00', 'UTC'),
            'timezone' => 'America/New_York',
        ]);

        $this->actingAs($user);

        Livewire::test(EditBooking::class, ['record' => $booking->getRouteKey()])
            ->assertFormSet([
                'start_at' => '2026-10-20 13:00:00',
                'end_at' => '2026-10-20 16:00:00',
            ])
            ->set('data.start_at', '2026-10-20 10:00:00')
            ->assertFormSet([
                'end_at' => '2026-10-20 17:00:00',
            ]);
    }

    public function test_team_member_cannot_create_a_booking(): void
    {
        $user = User::factory()->teamMember()->create();

        $this->actingAs($user)
            ->get('/manage/bookings/create')
            ->assertForbidden();
    }
}
