<?php

namespace App\Filament\Teams\Widgets\Manager;

use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Support\ManagerAttention;

class ManagerAllCaughtUpWidget extends ManagerSection
{
    protected static ?int $sort = 1;

    protected string $view = 'filament.teams.widgets.manager.all-caught-up';

    protected function sectionData(): array
    {
        $attention = $this->attention();
        $canBrowseBookings = static::canViewBookingTriage();

        return [
            'caughtUp' => $attention->outstandingDebriefs()->isEmpty()
                && $attention->needsLead()->isEmpty()
                && $attention->readyToConfirm()->isEmpty()
                && $attention->needsStaffing()->isEmpty()
                && $attention->bouncedMembers()->isEmpty(),
            'bookingsUrl' => $canBrowseBookings
                ? BookingResource::getUrl('index', [
                    'tableFilters' => [
                        'status' => ['value' => 'tentative'],
                    ],
                ])
                : null,
            'previewLimit' => ManagerAttention::PREVIEW_LIMIT,
        ];
    }
}
