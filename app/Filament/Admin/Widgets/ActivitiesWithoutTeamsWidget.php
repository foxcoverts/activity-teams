<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Activities\ActivityResource;
use App\Models\User;
use App\Support\AdminAttention;
use App\Support\Palette;
use Filament\Support\Icons\Heroicon;

class ActivitiesWithoutTeamsWidget extends AdminSection
{
    protected static ?int $sort = 6;

    protected string $view = 'filament.admin.widgets.simple-attention-list';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSystemAdmin();
    }

    protected function sectionData(): array
    {
        $rows = $this->attention()->activitiesWithoutTeams();
        $total = $rows->count();

        return [
            'heading' => __('Activities not on a team'),
            'icon' => Heroicon::OutlinedFlag,
            'iconColor' => Palette::uncertain(),
            'rows' => $rows->take(AdminAttention::PREVIEW_LIMIT)->values(),
            'total' => $total,
            'rowKeyPrefix' => 'activity-no-team',
            'recordUrl' => fn ($record): string => ActivityResource::getUrl('view', [
                'record' => $record,
                'relation' => 'teams',
            ]),
            'seeAllUrl' => ActivityResource::getUrl('index', [
                'filters' => [
                    'without_team' => ['isActive' => true],
                ],
            ]),
            'seeAllLabel' => __('See all activities not on a team'),
            'previewLimit' => AdminAttention::PREVIEW_LIMIT,
        ];
    }
}
