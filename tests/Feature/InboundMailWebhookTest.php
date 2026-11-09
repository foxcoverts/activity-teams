<?php

namespace Tests\Feature;

use App\Enums\InboundMailOutcome;
use App\Filament\Admin\Resources\InboundMails\Pages\ListInboundMails;
use App\Models\InboundMail;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InboundMailWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_rejects_missing_secret(): void
    {
        $this->postJson(route('api.mail.store'), $this->payload())
            ->assertUnauthorized();

        $this->assertDatabaseCount('inbound_mails', 0);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $payload = $this->payload();
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->call('POST', route('api.mail.store'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => 'not-a-valid-signature',
        ], $body)->assertUnauthorized();

        $this->assertDatabaseCount('inbound_mails', 0);
    }

    public function test_webhook_stores_authenticated_payload_for_review(): void
    {
        $payload = $this->payload();

        $this->postMailWebhook($payload)->assertOk();

        $mail = InboundMail::query()->first();

        $this->assertNotNull($mail);
        $this->assertSame('member@example.com', $mail->from_address);
        $this->assertSame('booking+abc@climbfoxcoverts.co.uk', $mail->to_address);
        $this->assertSame(InboundMailOutcome::Queued, $mail->outcome);
        $this->assertNull($mail->handled_at);
    }

    public function test_webhook_accepts_bearer_token_fallback(): void
    {
        $this->withToken('testing-webhook-secret')
            ->postJson(route('api.mail.store'), $this->payload())
            ->assertOk();

        $this->assertDatabaseCount('inbound_mails', 1);
    }

    public function test_system_admin_can_mark_inbound_mail_reviewed(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $mail = InboundMail::factory()->create();

        Filament::setCurrentPanel('admin');

        Livewire::actingAs($admin)
            ->test(ListInboundMails::class)
            ->callTableAction('markReviewed', $mail);

        $this->assertNotNull($mail->fresh()->reviewed_at);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'subject' => 'Hello',
            'date' => now()->toIso8601String(),
            'from' => [
                'text' => 'member@example.com',
                'value' => [['address' => 'member@example.com', 'name' => 'Member']],
            ],
            'to' => [
                'text' => 'booking+abc@climbfoxcoverts.co.uk',
                'value' => [['address' => 'booking+abc@climbfoxcoverts.co.uk', 'name' => null]],
            ],
            'attachments' => [],
            'html' => '<p>Hi</p>',
        ];
    }
}
