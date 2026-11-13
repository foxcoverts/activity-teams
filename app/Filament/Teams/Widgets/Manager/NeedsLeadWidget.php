<?php

namespace App\Filament\Teams\Widgets\Manager;

use App\Enums\TentativeTriage;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Support\ManagerAttention;
use Filament\Support\Icons\Heroicon;

class NeedsLeadWidget extends ManagerSection
{
    protected static ?int $sort = 3;

    protected string $view = 'filament.teams.widgets.manager.booking-section';

    public static function canView(): bool
    {
        return static::canViewBookingTriage();
    }

    protected function sectionData(): array
    {
        $rows = $this->attention()->needsLead();
        $total = $rows->count();

        return [
            'heading' => TentativeTriage::NeedsLead->getLabel(),
            'icon' => Heroicon::OutlinedExclamationTriangle,
            'iconColor' => TentativeTriage::NeedsLead->severity()->color(),
            'rows' => $rows->take(ManagerAttention::PREVIEW_LIMIT)->values(),
            'total' => $total,
            'triage' => TentativeTriage::NeedsLead,
            'seeAllUrl' => BookingResource::getUrl('index', [
                'tableFilters' => [
                    'status' => ['value' => 'tentative'],
                ],
            ]),
            'seeAllLabel' => __('See all tentative bookings'),
            'previewLimit' => ManagerAttention::PREVIEW_LIMIT,
        ];
    }
}
