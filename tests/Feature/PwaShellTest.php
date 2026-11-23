<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Filament\Member\Pages\Dashboard;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PwaShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_manifest_starts_at_the_member_dashboard(): void
    {
        $manifestPath = public_path('site.webmanifest');

        $this->assertFileExists($manifestPath);

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        $this->assertIsArray($manifest);
        $this->assertSame('/dashboard', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('/', $manifest['id']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame(Dashboard::getUrl(parameters: [], isAbsolute: false), $manifest['start_url']);
    }

    public function test_welcome_page_registers_the_service_worker_and_links_the_manifest(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('site.webmanifest', false)
            ->assertSee('serviceWorker', false)
            ->assertSee('sw.js', false);
    }

    public function test_offline_fallback_page_exists_for_the_service_worker(): void
    {
        $path = public_path('offline.html');

        $this->assertFileExists($path);

        $html = (string) file_get_contents($path);

        $this->assertStringContainsString('You are offline', $html);
        $this->assertStringContainsString('not available offline yet', $html);
        $this->assertFileExists(public_path('sw.js'));
    }

    public function test_member_login_redirects_to_the_dashboard_not_the_public_landing(): void
    {
        $user = User::factory()->teamMember()->create([
            'email' => 'member@example.test',
        ]);

        Filament::setCurrentPanel('member');

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'member@example.test',
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect(Dashboard::getUrl());

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame(url('/'), Dashboard::getUrl());
    }

    public function test_signed_in_member_visiting_login_is_sent_to_the_dashboard(): void
    {
        $member = User::factory()->teamMember()->create();

        Filament::setCurrentPanel('member');
        $this->actingAs($member);

        $this->get('/login')
            ->assertRedirect(Dashboard::getUrl());
    }
}
