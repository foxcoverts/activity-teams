<?php

namespace App\Filament\Teams\Widgets;

use App\Filament\Member\Widgets\ThisWeekWidget as MemberThisWeekWidget;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Filament\Teams\Widgets\Concerns\ScopedToTeam;

class ThisWeekWidget extends MemberThisWeekWidget
{
    use ScopedToTeam;

    protected function sectionData(): array
    {
        return [
            ...parent::sectionData(),
            'rotaUrl' => BookingResource::getUrl('index', ['tab' => 'rota']),
        ];
    }
}
