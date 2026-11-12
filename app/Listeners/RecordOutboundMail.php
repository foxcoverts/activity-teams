<?php

namespace App\Listeners;

use App\Enums\OutboundMailStatus;
use App\Models\OutboundMail;
use App\Models\User;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\IdentificationHeader;

class RecordOutboundMail
{
    public function handle(MessageSending $event): void
    {
        $message = $event->message;
        $messageId = $this->ensureMessageId($message);

        if ($messageId === null) {
            return;
        }

        $mailable = is_string($event->data['__laravel_mailable'] ?? null)
            ? $event->data['__laravel_mailable']
            : null;

        $recipients = collect([
            ...$message->getTo(),
            ...$message->getCc(),
            ...$message->getBcc(),
        ])
            ->map(fn (Address $address): string => strtolower($address->getAddress()))
            ->unique()
            ->values();

        foreach ($recipients as $recipient) {
            OutboundMail::query()->firstOrCreate(
                ['message_id' => $messageId, 'recipient' => $recipient],
                [
                    'user_id' => User::query()->where('email', $recipient)->value('id'),
                    'status' => OutboundMailStatus::Sent,
                    'mailable' => $mailable,
                ],
            );
        }
    }

    private function ensureMessageId(Email $message): ?string
    {
        $headers = $message->getHeaders();

        if (! $headers->has('Message-ID')) {
            $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
            $headers->addIdHeader('Message-ID', Str::uuid().'@'.$host);
        }

        $header = $headers->get('Message-ID');

        if (! $header instanceof IdentificationHeader) {
            return OutboundMail::normalizeMessageId($headers->getHeaderBody('Message-ID'));
        }

        return OutboundMail::normalizeMessageId($header->getId());
    }
}
