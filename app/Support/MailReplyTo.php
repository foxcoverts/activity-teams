<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Key;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Mail\Mailables\Address;

class MailReplyTo
{
    public static function booking(Booking $booking): Address
    {
        return new Address(InboundMailAddress::forBooking($booking));
    }

    public static function key(Key $ring): Address
    {
        return new Address(InboundMailAddress::forKey($ring));
    }

    public static function team(Team $team): Address
    {
        return new Address(InboundMailAddress::forTeam($team));
    }

    public static function forTeamInvitation(TeamInvitation $invitation): ?Address
    {
        if ($invitation->isSiteInvitation() || $invitation->team === null) {
            return null;
        }

        return self::team($invitation->team);
    }
}
