<?php

namespace App\Support;

use App\Models\Team;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;

class TeamMailIdentity
{
    public static function from(Team $team): Address
    {
        return new Address($team->mailFromAddress(), $team->mailFromName());
    }

    public static function applyToEnvelope(Envelope $envelope, Team $team, ?Address $operationalReplyTo = null): Envelope
    {
        $from = self::from($team);
        $replyTo = $operationalReplyTo ?? $from;

        return new Envelope(
            from: $from,
            to: $envelope->to,
            cc: $envelope->cc,
            bcc: $envelope->bcc,
            replyTo: [$replyTo],
            subject: $envelope->subject,
            tags: $envelope->tags,
            metadata: $envelope->metadata,
            using: $envelope->using,
        );
    }
}
