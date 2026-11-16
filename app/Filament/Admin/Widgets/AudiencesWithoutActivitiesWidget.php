<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Audiences\AudienceResource;
use App\Models\User;
use App\Support\AdminAttention;
use App\Support\Palette;
use Filament\Support\Icons\Heroicon;

class AudiencesWithoutActivitiesWidget extends AdminSection
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
        $rows = $this->attention()->audiencesWithoutActivities();
        $total = $rows->count();

        return [
            'heading' => __('Audiences not on an activity'),
            'icon' => Heroicon::OutlinedUserGroup,
            'iconColor' => Palette::uncertain(),
            'rows' => $rows->take(AdminAttention::PREVIEW_LIMIT)->values(),
            'total' => $total,
            'rowKeyPrefix' => 'audience-no-activity',
            'recordUrl' => fn ($record): string => AudienceResource::getUrl('view', ['record' => $record]),
            'seeAllUrl' => AudienceResource::getUrl('index', [
                'tableFilters' => [
                    'without_activity' => ['isActive' => true],
                ],
            ]),
            'seeAllLabel' => __('See all audiences not on an activity'),
            'previewLimit' => AdminAttention::PREVIEW_LIMIT,
        ];
    }
}
