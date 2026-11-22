<?php

namespace Tests\Feature;

use App\Filament\Member\Resources\Bookings\BookingResource as MemberBookingResource;
use App\Filament\Teams\Resources\Bookings\BookingResource as TeamsBookingResource;
use App\Models\Activity;
use App\Models\Audience;
use App\Models\Booking;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingGlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_teams_global_search_matches_bookings_by_activity_label_without_querying_display_name(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $audience = Audience::factory()->create(['name' => 'Scouts']);
        $activity = Activity::factory()->withAudience($audience)->create(['name' => 'Wall']);
        $booking = Booking::factory()->forTeam($team)->confirmed()->create([
            'activity_id' => $activity->getKey(),
            'activity_label' => 'Climbing',
            'audience_id' => $audience->getKey(),
            'group_name' => '1st Anytown Scouts',
        ]);

        $this->actingAs($manager);

        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        $results = TeamsBookingResource::getGlobalSearchResults('climbing');

        $this->assertCount(1, $results);
        $this->assertSame($booking->display_name, $results->first()->title);
        $this->assertSame(
            TeamsBookingResource::getUrl('view', ['record' => $booking]),
            $results->first()->url,
        );
    }

    public function test_teams_global_search_matches_bookings_by_group_name(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->firstOrFail();
        $audience = Audience::factory()->create(['name' => 'Scouts']);
        $activity = Activity::factory()->withAudience($audience)->create();
        Booking::factory()->forTeam($team)->confirmed()->create([
            'activity_id' => $activity->getKey(),
            'activity_label' => 'Climbing',
            'audience_id' => $audience->getKey(),
            'group_name' => '1st Anytown Scouts',
        ]);

        $this->actingAs($manager);

        Filament::setCurrentPanel('teams');
        Filament::setTenant($team);

        $results = TeamsBookingResource::getGlobalSearchResults('Anytown');

        $this->assertCount(1, $results);
        $this->assertSame('Climbing · 1st Anytown Scouts', $results->first()->title);
    }

    public function test_member_global_search_matches_bookings_by_audience_name(): void
    {
        $member = User::factory()->teamMember()->create();
        $team = $member->teams()->firstOrFail();
        $audience = Audience::factory()->create(['name' => 'Explorers']);
        $activity = Activity::factory()->withAudience($audience)->create();
        Booking::factory()->forTeam($team)->confirmed()->create([
            'activity_id' => $activity->getKey(),
            'activity_label' => 'Climbing',
            'audience_id' => $audience->getKey(),
            'group_name' => null,
        ]);

        $this->actingAs($member);

        Filament::setCurrentPanel('member');

        $results = MemberBookingResource::getGlobalSearchResults('Explorers');

        $this->assertCount(1, $results);
        $this->assertSame('Climbing · Explorers', $results->first()->title);
    }
}
