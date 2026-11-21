<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Venues\VenueResource;
use App\Models\User;
use App\Support\AdminAttention;
use App\Support\Palette;
use Filament\Support\Icons\Heroicon;

class VenuesWithoutTeamsWidget extends AdminSection
{
    protected static ?int $sort = 7;

    protected string $view = 'filament.admin.widgets.simple-attention-list';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSystemAdmin();
    }

    protected function sectionData(): array
    {
        $rows = $this->attention()->venuesWithoutTeams();
        $total = $rows->count();

        return [
            'heading' => __('Venues not on a team'),
            'icon' => Heroicon::OutlinedMapPin,
            'iconColor' => Palette::uncertain(),
            'rows' => $rows->take(AdminAttention::PREVIEW_LIMIT)->values(),
            'total' => $total,
            'rowKeyPrefix' => 'venue-no-team',
            'recordUrl' => fn ($record): string => VenueResource::getUrl('view', [
                'record' => $record,
                'relation' => 'teams',
            ]),
            'seeAllUrl' => VenueResource::getUrl('index', [
                'filters' => [
                    'without_team' => ['isActive' => true],
                ],
            ]),
            'seeAllLabel' => __('See all venues not on a team'),
            'previewLimit' => AdminAttention::PREVIEW_LIMIT,
        ];
    }
}
