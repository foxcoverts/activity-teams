<?php

namespace App\Filament\Member\Widgets;

use App\Filament\Member\Resources\Bookings\BookingResource;

class AllCaughtUpWidget extends DashboardSection
{
    protected static ?int $sort = 5;

    protected string $view = 'filament.member.widgets.all-caught-up';

    protected function sectionData(): array
    {
        return [
            'caughtUp' => ! $this->dashboard()->hasAttention(),
            'bookingsUrl' => BookingResource::getUrl('index'),
        ];
    }
}
