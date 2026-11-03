<?php

namespace Tests\Feature;

use App\Filament\Member\Pages\Dashboard;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_the_public_climbing_welcome_page(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(__('Fox Coverts Climbing'))
            ->assertSee(__('Climbing and Abseiling sessions at Fox Coverts Campsite are run by our team of qualified climbing instructors.'), false)
            ->assertSee(__('Go to Team Dashboard'))
            ->assertSee(Dashboard::getUrl(), false)
            ->assertSee('https://foxcoverts.org.uk/activities/climbing/', false)
            ->assertDontSee('fi-simple-main', false);
    }

    public function test_guests_are_sent_to_login_from_the_member_dashboard(): void
    {
        $this->get(Dashboard::getUrl())
            ->assertRedirect('/login');
    }

    public function test_a_signed_in_member_can_open_the_dashboard(): void
    {
        $member = User::factory()->teamMember()->create();

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($member);

        $this->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee(__('Dashboard'));
    }
}
