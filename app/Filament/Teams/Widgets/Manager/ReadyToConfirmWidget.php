<?php

namespace App\Filament\Teams\Widgets\Manager;

use App\Enums\TentativeTriage;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Support\ManagerAttention;
use App\Support\Palette;
use Filament\Support\Icons\Heroicon;

class ReadyToConfirmWidget extends ManagerSection
{
    protected static ?int $sort = 4;

    protected string $view = 'filament.teams.widgets.manager.booking-section';

    public static function canView(): bool
    {
        return static::canViewBookingTriage();
    }

    protected function sectionData(): array
    {
        $rows = $this->attention()->readyToConfirm();
        $total = $rows->count();

        return [
            'heading' => __('Tentative — Ready to confirm'),
            'icon' => Heroicon::OutlinedCheckCircle,
            'iconColor' => Palette::positive(),
            'rows' => $rows->take(ManagerAttention::PREVIEW_LIMIT)->values(),
            'total' => $total,
            'triage' => TentativeTriage::ReadyToConfirm,
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
