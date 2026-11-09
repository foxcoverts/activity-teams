<?php

namespace Tests;

use App\Models\Team;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\WithCachedConfig;
use Illuminate\Foundation\Testing\WithCachedRoutes;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Testing\TestResponse;
use JsonException;

abstract class TestCase extends BaseTestCase
{
    use WithCachedConfig;
    use WithCachedRoutes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Sleep::fake(syncWithCarbon: true);
        Http::preventStrayRequests();
    }

    public function actingAs(Authenticatable $user, $guard = null): static
    {
        $result = parent::actingAs($user, $guard);

        if (Filament::getCurrentPanel()?->getId() === 'teams') {
            $team = $user->managedTeams()->first();

            if ($team === null) {
                $team = $user->teams()->first();
            }

            if ($team === null && $user->isSystemAdmin()) {
                $team = Team::first();
            }

            Filament::setTenant($team instanceof Team ? $team : null);
        }

        return $result;
    }

    /**
     * POST a ForwardEmail-style signed inbound mail webhook payload.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    protected function postMailWebhook(array $payload): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, (string) config('mail.webhook_secret'));

        return $this->call('POST', route('api.mail.store'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
        ], $body);
    }
}
