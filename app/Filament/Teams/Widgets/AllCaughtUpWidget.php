<?php

namespace App\Filament\Teams\Widgets;

use App\Filament\Member\Widgets\AllCaughtUpWidget as MemberAllCaughtUpWidget;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Filament\Teams\Widgets\Concerns\ScopedToTeam;

class AllCaughtUpWidget extends MemberAllCaughtUpWidget
{
    use ScopedToTeam;

    protected function sectionData(): array
    {
        return [
            ...parent::sectionData(),
            'bookingsUrl' => BookingResource::getUrl('index'),
        ];
    }
}
