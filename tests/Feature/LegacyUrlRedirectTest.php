<?php

namespace Tests\Feature;

use App\Filament\Member\Resources\Keys\KeyResource;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Filament\Teams\Resources\NewsPosts\NewsPostResource;
use App\Filament\Teams\Resources\Tasks\TaskResource;
use App\Models\Booking;
use App\Models\Key;
use App\Models\NewsPost;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyUrlRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_redirects_to_teams_booking_view(): void
    {
        $team = Team::factory()->create();
        $booking = Booking::factory()->forTeam($team)->create();

        $this->get('/booking/'.$booking->getRouteKey())
            ->assertRedirect(BookingResource::getUrl(
                'view',
                ['record' => $booking],
                panel: 'teams',
                tenant: $team,
            ))
            ->assertStatus(301);
    }

    public function test_booking_preview_redirects_to_teams_booking_view(): void
    {
        $team = Team::factory()->create();
        $booking = Booking::factory()->forTeam($team)->create();

        $this->get('/booking/'.$booking->getRouteKey().'/preview')
            ->assertRedirect(BookingResource::getUrl(
                'view',
                ['record' => $booking],
                panel: 'teams',
                tenant: $team,
            ))
            ->assertStatus(301);
    }

    public function test_booking_share_redirects_to_teams_booking_view(): void
    {
        $team = Team::factory()->create();
        $booking = Booking::factory()->forTeam($team)->create();

        $this->get('/booking/'.$booking->getRouteKey().'/share')
            ->assertRedirect(BookingResource::getUrl(
                'view',
                ['record' => $booking],
                panel: 'teams',
                tenant: $team,
            ))
            ->assertStatus(301);
    }

    public function test_news_redirects_to_teams_news_view(): void
    {
        $team = Team::factory()->create();
        $post = NewsPost::factory()->forTeam($team)->published()->create([
            'slug' => 'legacy-news-post',
        ]);

        $this->get('/news/legacy-news-post')
            ->assertRedirect(NewsPostResource::getUrl(
                'view',
                ['record' => $post],
                panel: 'teams',
                tenant: $team,
            ))
            ->assertStatus(301);
    }

    public function test_todo_redirects_to_teams_task_view(): void
    {
        $team = Team::factory()->create();
        $task = Task::factory()->forTeam($team)->create();

        $this->get('/todo/'.$task->getRouteKey())
            ->assertRedirect(TaskResource::getUrl(
                'view',
                ['record' => $task],
                panel: 'teams',
                tenant: $team,
            ))
            ->assertStatus(301);
    }

    public function test_key_redirects_to_member_key_view(): void
    {
        $key = Key::factory()->create();

        $this->get('/key/'.$key->getRouteKey())
            ->assertRedirect(KeyResource::getUrl('view', ['record' => $key], panel: 'member'))
            ->assertStatus(301);
    }

    public function test_singular_booking_ics_redirects_to_plural_path(): void
    {
        $booking = Booking::factory()->forTeam(Team::factory()->create())->create();

        $this->get('/booking/'.$booking->getRouteKey().'.ics')
            ->assertRedirect(route('booking.show.ics', $booking))
            ->assertStatus(301);
    }

    public function test_singular_ical_feed_redirects_to_plural_bookings_feed(): void
    {
        $user = User::factory()->create([
            'feed_token' => User::generateToken(),
        ]);

        $this->get('/ical/'.$user->feed_token.'/booking.ics')
            ->assertRedirect(route('booking.ics', $user))
            ->assertStatus(301);
    }

    public function test_privacy_policy_redirects_to_privacy(): void
    {
        $this->get('/privacy-policy')
            ->assertRedirect('/privacy')
            ->assertStatus(301);
    }

    public function test_unknown_booking_returns_404(): void
    {
        $this->get('/booking/01missingbookingulid000000000')
            ->assertNotFound();
    }

    public function test_unknown_news_returns_404(): void
    {
        $this->get('/news/does-not-exist')
            ->assertNotFound();
    }
}
