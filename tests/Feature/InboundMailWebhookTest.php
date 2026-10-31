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

    public function test_webhook_stores_authenticated_payload_for_review(): void
    {
        $payload = $this->payload();

        $this->withToken('testing-webhook-secret')
            ->postJson(route('api.mail.store'), $payload)
            ->assertOk();

        $mail = InboundMail::query()->first();

        $this->assertNotNull($mail);
        $this->assertSame('member@example.com', $mail->from_address);
        $this->assertSame('booking+abc@climbfoxcoverts.co.uk', $mail->to_address);
        $this->assertSame(InboundMailOutcome::Queued, $mail->outcome);
        $this->assertNull($mail->handled_at);
    }

    public function test_system_admin_can_mark_inbound_mail_reviewed(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $mail = InboundMail::factory()->create();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

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
