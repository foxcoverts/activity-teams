<?php

namespace Tests\Feature;

use App\Filament\Teams\Resources\Bookings\Pages\ViewBooking;
use App\Models\Audience;
use App\Models\Booking;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingTitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_titles_prefer_group_over_audience_and_always_include_activity(): void
    {
        $audience = Audience::factory()->create(['name' => 'Scouts']);
        $booking = Booking::factory()->create([
            'activity_label' => 'Climbing',
            'audience_id' => $audience->getKey(),
            'group_name' => '1st Anytown Scouts',
        ]);

        $this->assertSame(['Climbing', '1st Anytown Scouts'], $booking->titleParts());
        $this->assertSame('Climbing · 1st Anytown Scouts', $booking->display_name);
        $this->assertSame('Climbing — 1st Anytown Scouts', $booking->icsSummary());
        $this->assertStringContainsString('Climbing · 1st Anytown Scouts', $booking->relatedLabel());
        $this->assertStringNotContainsString('Scouts', str_replace('1st Anytown Scouts', '', $booking->relatedLabel()));
    }

    public function test_titles_use_audience_when_group_is_blank(): void
    {
        $audience = Audience::factory()->create(['name' => 'Scouts']);
        $booking = Booking::factory()->create([
            'activity_label' => 'Climbing',
            'audience_id' => $audience->getKey(),
            'group_name' => null,
        ]);

        $this->assertSame('Climbing · Scouts', $booking->display_name);
        $this->assertSame('Climbing — Scouts', $booking->icsSummary());
    }

    public function test_view_booking_heading_is_the_display_name_without_view_prefix(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $audience = Audience::factory()->create(['name' => 'Scouts']);
        $booking = Booking::factory()->forTeam($team)->create([
            'activity_label' => 'Climbing',
            'audience_id' => $audience->getKey(),
            'group_name' => '1st Anytown Scouts',
        ]);

        $this->actingAs($manager);

        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        $component = Livewire::test(ViewBooking::class, ['record' => $booking->getKey()]);

        $this->assertSame('Climbing · 1st Anytown Scouts', $component->instance()->getTitle());
        $component->assertDontSee('View Climbing · 1st Anytown Scouts', false);
    }
}
