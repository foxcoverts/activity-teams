<?php

namespace App\Filament\Member\Widgets;

use App\Filament\Member\Resources\Bookings\BookingResource;

class ThisWeekWidget extends DashboardSection
{
    use RespondsFromDashboard;

    protected static ?int $sort = 2;

    protected string $view = 'filament.member.widgets.this-week';

    protected function sectionData(): array
    {
        return [
            ...$this->dashboard()->thisWeek(),
            'rotaUrl' => BookingResource::getUrl('index', ['tab' => 'rota']),
        ];
    }
}
