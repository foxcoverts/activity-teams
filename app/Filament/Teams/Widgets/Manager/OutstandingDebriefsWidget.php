<?php

namespace App\Filament\Teams\Widgets\Manager;

use App\Enums\TeamFeature;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use App\Models\Team;
use App\Support\ManagerAttention;
use App\Support\Palette;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;

class OutstandingDebriefsWidget extends ManagerSection
{
    protected static ?int $sort = 2;

    protected string $view = 'filament.teams.widgets.manager.booking-section';

    public static function canView(): bool
    {
        if (! static::canViewBookingTriage()) {
            return false;
        }

        $team = Filament::getTenant();

        return $team instanceof Team && $team->featureEnabled(TeamFeature::Debrief);
    }

    protected function sectionData(): array
    {
        $rows = $this->attention()->outstandingDebriefs();
        $total = $rows->count();

        return [
            'heading' => __('Outstanding debriefs'),
            'icon' => Heroicon::OutlinedDocumentCheck,
            'iconColor' => Palette::uncertain(),
            'rows' => $rows->take(ManagerAttention::PREVIEW_LIMIT)->values(),
            'total' => $total,
            'triage' => null,
            'seeAllUrl' => BookingResource::getUrl('index'),
            'seeAllLabel' => __('See all bookings'),
            'previewLimit' => ManagerAttention::PREVIEW_LIMIT,
        ];
    }
}
