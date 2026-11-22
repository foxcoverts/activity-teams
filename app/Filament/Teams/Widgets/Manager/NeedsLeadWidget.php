<?php

namespace App\Filament\Teams\Widgets\Manager;

use App\Enums\AttentionSeverity;
use App\Enums\TentativeTriage;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Models\Booking;
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
        $attention = $this->attention();
        $rows = $attention->needsLead();
        $total = $rows->count();
        $near = $rows->contains(
            fn (Booking $booking): bool => $attention->isWithinAtRiskWindow($booking),
        );

        return [
            'heading' => TentativeTriage::NeedsLead->getLabel(),
            'icon' => Heroicon::OutlinedExclamationTriangle,
            'iconColor' => ($near ? AttentionSeverity::Destructive : AttentionSeverity::Uncertain)->color(),
            'rows' => $rows->take(ManagerAttention::PREVIEW_LIMIT)->values(),
            'total' => $total,
            'triage' => TentativeTriage::NeedsLead,
            'attention' => $attention,
            'seeAllUrl' => BookingResource::getUrl('index', [
                'filters' => [
                    'status' => ['value' => 'tentative'],
                ],
            ]),
            'seeAllLabel' => __('See all tentative bookings'),
            'previewLimit' => ManagerAttention::PREVIEW_LIMIT,
        ];
    }
}
