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
     * @param  list<string>|null  $invitationNudges
     * @param  list<string>|null  $sessionReminders
     * @param  list<string>|null  $attentionNew
     * @param  list<string>|null  $attentionOutstanding
     * @param  list<string>|null  $debriefsDue
     * @param  list<string>|null  $logbookDue
     */
    public function __construct(
        public ?int $outstanding,
        public int $unreadRemainder,
        public ?array $rota,
        public ?array $invitationNudges,
        public ?array $sessionReminders,
        public ?array $attentionNew,
        public ?array $attentionOutstanding,
        public ?array $debriefsDue,
        public ?array $logbookDue,
        public string $inboxUrl,
        public string $dashboardUrl,
        public ?string $managerUrl,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        if ($this->outstanding !== null) {
            return new Envelope(subject: trans_choice(
                'You have :count new notification|You have :count new notifications',
                $this->outstanding,
                ['count' => $this->outstanding],
            ));
        }

        if ($this->attentionNew !== null) {
            return new Envelope(subject: __('Manager attention'));
        }

        if ($this->invitationNudges !== null || $this->sessionReminders !== null) {
            return new Envelope(subject: __('Booking catch-up'));
        }

        if ($this->debriefsDue !== null) {
            return new Envelope(subject: __('Debriefs still due'));
        }

        if ($this->logbookDue !== null) {
            return new Envelope(subject: __('Logbook entries due'));
        }

        return new Envelope(subject: __('Your rota'));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.digest.combined',
        );
    }
}
