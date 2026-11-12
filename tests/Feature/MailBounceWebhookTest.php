<?php

namespace Tests\Feature;

use App\Actions\ProcessMailBounceAction;
use App\Enums\OutboundMailStatus;
use App\Enums\PersonalDataSection;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Filament\Teams\Resources\Members\Pages\ViewMember;
use App\Mail\LeadNeededMail;
use App\Models\Booking;
use App\Models\MailBounce;
use App\Models\OutboundMail;
use App\Models\User;
use App\Notifications\EmailBouncedNotification;
use App\Notifications\LeadNeededNotification;
use App\Support\BounceReport;
use App\Support\CombinedDigest;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use JsonException;
use Livewire\Livewire;
use Tests\TestCase;

class MailBounceWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_bounce_webhook_rejects_invalid_signature(): void
    {
        $this->call('POST', route('api.webhooks.forwardemail.bounces'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => 'not-a-valid-signature',
        ], json_encode($this->hardBouncePayload('member@example.com'), JSON_THROW_ON_ERROR))
            ->assertUnauthorized();

        $this->assertDatabaseCount('mail_bounces', 0);
    }

    public function test_canonical_and_deprecated_inbound_routes_accept_signed_payloads(): void
    {
        $payload = [
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

        $this->postMailWebhook($payload)->assertOk();
        $this->postSignedWebhook(route('api.webhooks.forwardemail.mail'), $payload)->assertOk();

        $this->assertDatabaseCount('inbound_mails', 2);
    }

    public function test_forward_email_hard_bounce_flags_user_and_notifies_managers(): void
    {
        Notification::fake();

        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $member = User::factory()->teamMember($team)->create([
            'email' => 'bouncer@example.com',
        ]);

        $outbound = OutboundMail::factory()->forUser($member)->create([
            'message_id' => 'abc123@climbfoxcoverts.co.uk',
            'mailable' => LeadNeededMail::class,
        ]);

        $this->postBounceWebhook($this->hardBouncePayload(
            recipient: $member->email,
            messageId: '<ABC123@climbfoxcoverts.co.uk>',
            emailId: 'fe-email-1',
        ))->assertOk();

        $member->refresh();
        $outbound->refresh();

        $this->assertNotNull($member->email_bounced_at);
        $this->assertSame('recipient', $member->email_bounce_category);
        $this->assertSame(OutboundMailStatus::Bounced, $outbound->status);
        $this->assertDatabaseHas('mail_bounces', [
            'outbound_mail_id' => $outbound->getKey(),
            'provider' => 'forwardemail',
            'provider_message_id' => 'fe-email-1',
            'hard' => true,
            'category' => 'recipient',
        ]);

        Notification::assertSentTo($manager, EmailBouncedNotification::class, function (EmailBouncedNotification $notification) use ($member, $manager, $team): bool {
            return $notification->member->is($member)
                && $notification->inboxUrl($manager) === MemberResource::getUrl('view', ['record' => $member], panel: 'teams', tenant: $team);
        });
    }

    public function test_soft_bounce_logs_without_flagging_the_user(): void
    {
        Notification::fake();

        $member = User::factory()->teamMember()->create([
            'email' => 'soft@example.com',
        ]);
        $outbound = OutboundMail::factory()->forUser($member)->create();

        $this->postBounceWebhook([
            'email_id' => 'fe-soft-1',
            'recipient' => $member->email,
            'message' => 'Mailbox full',
            'bounce' => [
                'action' => 'delayed',
                'category' => 'capacity',
                'message' => 'Mailbox full',
            ],
            'headers' => [
                'Message-ID' => '<'.$outbound->message_id.'>',
            ],
            'bounced_at' => now()->toIso8601String(),
        ])->assertOk();

        $member->refresh();
        $outbound->refresh();

        $this->assertNull($member->email_bounced_at);
        $this->assertSame(OutboundMailStatus::Bounced, $outbound->status);
        $this->assertDatabaseHas('mail_bounces', [
            'outbound_mail_id' => $outbound->getKey(),
            'hard' => false,
            'category' => 'capacity',
        ]);
        Notification::assertNothingSent();
    }

    public function test_process_mail_bounce_releases_matching_inbox_row(): void
    {
        $manager = User::factory()->teamManager()->create();
        $booking = Booking::factory()->create(['team_id' => $manager->managedTeams()->first()->getKey()]);
        $manager->notify(new LeadNeededNotification($booking, null));

        Mail::fake();
        app(CombinedDigest::class)->send($manager);

        // Mail::fake() skips MessageSending; seed the outbound row the digest would have recorded.
        $row = $manager->notifications()->first();
        $outbound = OutboundMail::factory()->forUser($manager)->create([
            'mailable' => LeadNeededMail::class,
        ]);
        $row->forceFill(['outbound_message_id' => $outbound->message_id])->save();

        app(ProcessMailBounceAction::class)->handle(BounceReport::fromNormalized(
            recipient: (string) $manager->email,
            messageId: $outbound->message_id,
            category: 'block',
            action: 'failed',
            message: 'Blocked',
            provider: 'forwardemail',
            providerMessageId: 'inbox-release-1',
        ));

        $row->refresh();

        $this->assertNull($row->read_at);
        $this->assertNull($row->digest_notified_at);
    }

    public function test_changing_email_clears_bounce_flag(): void
    {
        $member = User::factory()->create([
            'email' => 'old@example.com',
            'email_bounced_at' => now(),
            'email_bounce_category' => 'recipient',
        ]);

        $member->forceFill(['email' => 'new@example.com'])->save();
        $member->refresh();

        $this->assertNull($member->email_bounced_at);
        $this->assertNull($member->email_bounce_category);
    }

    public function test_sending_mail_records_outbound_mail_with_message_id(): void
    {
        $member = User::factory()->create();

        Mail::to($member)->sendNow(new LeadNeededMail(
            Booking::factory()->create(),
            null,
        ));

        $outbound = OutboundMail::query()->where('recipient', strtolower((string) $member->email))->first();

        $this->assertNotNull($outbound);
        $this->assertSame(OutboundMailStatus::Sent, $outbound->status);
        $this->assertNotEmpty($outbound->message_id);
        $this->assertSame(LeadNeededMail::class, $outbound->mailable);
    }

    public function test_duplicate_provider_message_id_is_ignored(): void
    {
        $member = User::factory()->create(['email' => 'dup@example.com']);
        $outbound = OutboundMail::factory()->forUser($member)->create();

        $payload = $this->hardBouncePayload(
            recipient: $member->email,
            messageId: '<'.$outbound->message_id.'>',
            emailId: 'same-fe-id',
        );

        $this->postBounceWebhook($payload)->assertOk();
        $this->postBounceWebhook($payload)->assertOk();

        $this->assertSame(1, MailBounce::query()->count());
    }

    public function test_member_profile_shows_bounced_email_state(): void
    {
        $manager = User::factory()->teamManager()->create();
        $team = $manager->managedTeams()->first();
        $member = User::factory()->teamMember($team)->create([
            'email_bounced_at' => now(),
            'email_bounce_category' => 'recipient',
        ]);

        Filament::setCurrentPanel('teams');

        $this->actingAs($manager);

        Livewire::test(ViewMember::class, ['record' => $member->getKey()])
            ->call('acknowledgePersonalData', PersonalDataSection::ContactDetails->value)
            ->assertSee(__('Email bounced'))
            ->assertSee(__('Delivery failed for this address. Update it so the member does not miss invitations.'));
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    private function postBounceWebhook(array $payload): TestResponse
    {
        return $this->postSignedWebhook(route('api.webhooks.forwardemail.bounces'), $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    private function postSignedWebhook(string $uri, array $payload): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, (string) config('mail.webhook_secret'));

        return $this->call('POST', $uri, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
        ], $body);
    }

    /**
     * @return array<string, mixed>
     */
    private function hardBouncePayload(string $recipient, ?string $messageId = null, ?string $emailId = null): array
    {
        return [
            'email_id' => $emailId ?? 'fe-hard-1',
            'recipient' => $recipient,
            'message' => 'User unknown',
            'response' => '550 5.1.1 User unknown',
            'response_code' => 550,
            'bounce' => [
                'action' => 'failed',
                'category' => 'recipient',
                'message' => 'User unknown',
                'code' => 550,
                'status' => '5.1.1',
            ],
            'headers' => array_filter([
                'Message-ID' => $messageId,
            ]),
            'bounced_at' => now()->toIso8601String(),
        ];
    }
}
