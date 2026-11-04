<?php

namespace App\Filament\Teams\Widgets;

use App\Filament\Member\Widgets\NeedsResponseWidget as MemberNeedsResponseWidget;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Filament\Teams\Widgets\Concerns\ScopedToTeam;

class NeedsResponseWidget extends MemberNeedsResponseWidget
{
    use ScopedToTeam;

    protected function sectionData(): array
    {
        return [
            ...parent::sectionData(),
            'invitesUrl' => BookingResource::getUrl('index', ['tab' => 'invites']),
        ];
    }
}
