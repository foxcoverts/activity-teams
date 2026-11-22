<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Activities\ActivityResource;
use App\Models\User;
use App\Support\AdminAttention;
use App\Support\Palette;
use Filament\Support\Icons\Heroicon;

class ActivitiesWithoutAudiencesWidget extends AdminSection
{
    protected static ?int $sort = 8;

    protected string $view = 'filament.admin.widgets.simple-attention-list';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSystemAdmin();
    }

    protected function sectionData(): array
    {
        $rows = $this->attention()->activitiesWithoutAudiences();
        $total = $rows->count();

        return [
            'heading' => __('Activities without audiences'),
            'icon' => Heroicon::OutlinedFlag,
            'iconColor' => Palette::uncertain(),
            'rows' => $rows->take(AdminAttention::PREVIEW_LIMIT)->values(),
            'total' => $total,
            'rowKeyPrefix' => 'activity-no-audience',
            'recordUrl' => fn ($record): string => ActivityResource::getUrl('view', [
                'record' => $record,
                'relation' => 'audiences',
            ]),
            'seeAllUrl' => ActivityResource::getUrl('index', [
                'filters' => [
                    'without_audience' => ['isActive' => true],
                ],
            ]),
            'seeAllLabel' => __('See all activities without audiences'),
            'previewLimit' => AdminAttention::PREVIEW_LIMIT,
        ];
    }
}
