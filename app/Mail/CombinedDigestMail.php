<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CombinedDigestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{changed: bool, additions: list<string>, removals: list<string>, remaining: list<string>}|null  $rota
     */
    public function __construct(
        public ?int $outstanding,
        public int $unreadRemainder,
        public ?array $rota,
        public string $inboxUrl,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $subject = $this->outstanding === null
            ? __('Your rota')
            : trans_choice('You have :count new notification|You have :count new notifications', $this->outstanding, [
                'count' => $this->outstanding,
            ]);

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.digest.combined',
        );
    }
}
