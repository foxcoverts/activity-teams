<?php

namespace Tests\Feature;

use App\Filament\Auth\EditProfile;
use App\Livewire\ConfirmProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_due_confirmation_take_over_is_shown_instead_of_the_panel(): void
    {
        $user = User::factory()->teamMember()->create([
            'created_at' => now()->subYear()->subDay(),
            'profile_confirmed_at' => null,
        ]);

        $this->actingAs($user)
            ->get('/bookings')
            ->assertRedirect(route('profile.confirm'));
    }

    public function test_confirming_contact_details_clears_the_take_over(): void
    {
        $user = User::factory()->teamMember()->create([
            'created_at' => now()->subYear()->subDay(),
            'email' => 'old@example.com',
        ]);

        $this->actingAs($user);

        Livewire::test(ConfirmProfile::class)
            ->fillForm([
                'email' => 'old@example.com',
                'phone' => '+447700900111',
                'emergency_name' => 'Pat',
                'emergency_phone' => '+447700900222',
            ])
            ->call('confirm')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertNotNull($user->profile_confirmed_at);
        $this->assertSame('+447700900111', $user->phone);

        $this->get('/bookings')->assertOk();
    }

    public function test_deferring_allows_the_app_and_shows_a_banner_until_the_next_day(): void
    {
        $user = User::factory()->teamMember()->create([
            'created_at' => now()->subYear()->subDay(),
            'timezone' => 'Europe/London',
        ]);

        $this->actingAs($user);

        Livewire::test(ConfirmProfile::class)
            ->call('deferConfirmation');

        $this->get('/bookings')
            ->assertOk()
            ->assertSee('Please confirm your contact and emergency details.')
            ->assertSee(route('profile.confirm'), false);

        $this->travel(1)->day();

        $this->get('/bookings')
            ->assertRedirect(route('profile.confirm'));
    }

    public function test_saving_the_profile_counts_as_confirmation(): void
    {
        $user = User::factory()->teamMember()->create([
            'created_at' => now()->subYear()->subDay(),
        ]);

        Filament::setCurrentPanel(Filament::getPanel('member'));
        $this->actingAs($user);
        $user->deferProfileConfirmation();

        Livewire::test(EditProfile::class)
            ->fillForm([
                'name' => $user->name,
                'email' => $user->email,
                'timezone' => 'Europe/London',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($user->fresh()->profileConfirmationIsDue());
    }
}
