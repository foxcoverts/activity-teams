<?php

namespace App\Filament\Teams\Widgets\Manager;

use App\Enums\TentativeTriage;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Support\ManagerAttention;
use App\Support\Palette;
use Filament\Support\Icons\Heroicon;

class NeedsStaffingWidget extends ManagerSection
{
    protected static ?int $sort = 4;

    protected string $view = 'filament.teams.widgets.manager.needs-staffing';

    protected function sectionData(): array
    {
        $attention = $this->attention();
        $rows = $attention->needsStaffing();
        $total = $rows->count();

        return [
            'heading' => __('Tentative — Unstaffed / At risk'),
            'icon' => Heroicon::OutlinedClock,
            'iconColor' => Palette::uncertain(),
            'rows' => $rows->take(ManagerAttention::PREVIEW_LIMIT)->values(),
            'total' => $total,
            'attention' => $attention,
            'seeAllUrl' => BookingResource::getUrl('index', [
                'tableFilters' => [
                    'status' => ['value' => 'tentative'],
                ],
            ]),
            'seeAllLabel' => __('See all tentative bookings'),
            'previewLimit' => ManagerAttention::PREVIEW_LIMIT,
            'atRisk' => TentativeTriage::AtRisk,
            'unstaffed' => TentativeTriage::Unstaffed,
        ];
    }
}
