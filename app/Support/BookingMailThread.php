<?php

namespace App\Support;

use App\Models\Booking;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Str;

class BookingMailThread
{
    public static function envelope(Booking $booking, string $kind, ?Address $operationalReplyTo = null): Envelope
    {
        $booking->loadMissing('team');
        $envelope = new Envelope(
            subject: self::subject($booking, $kind),
        );

        if ($booking->team === null) {
            return $envelope;
        }

        return TeamMailIdentity::applyToEnvelope($envelope, $booking->team, $operationalReplyTo);
    }

    public static function headers(Booking $booking): Headers
    {
        return new Headers(
            references: [self::reference($booking)],
        );
    }

    public static function subject(Booking $booking, string $kind): string
    {
        $booking->loadMissing(['activity', 'venue']);
        $start = localDate($booking->start_at, $booking->timezone);

        return __(':kind: :activity at :venue, :start', [
            'kind' => $kind,
            'activity' => $booking->activityTitle(),
            'venue' => $booking->venue->name,
            'start' => $start->isoFormat('ddd D MMM YYYY HH:mm'),
        ]);
    }

    public static function reference(Booking $booking): string
    {
        return sprintf(
            'booking-%s-sequence-%s@%s',
            $booking->getKey(),
            $booking->sequence,
            Str::afterLast($booking->uid, '@'),
        );
    }
}
