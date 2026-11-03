<?php

namespace App\Filament\Member\Widgets;

use App\Filament\Member\Resources\Bookings\BookingResource;

class NeedsResponseWidget extends DashboardSection
{
    use RespondsFromDashboard;

    protected static ?int $sort = 3;

    protected string $view = 'filament.member.widgets.needs-response';

    protected function sectionData(): array
    {
        return [
            ...$this->dashboard()->needsResponse(),
            'invitesUrl' => BookingResource::getUrl('index', ['tab' => 'invites']),
        ];
    }
}
