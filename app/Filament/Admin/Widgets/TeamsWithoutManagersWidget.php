<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Teams\TeamResource;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Models\Team;
use App\Models\User;
use App\Support\AdminAttention;
use App\Support\Palette;
use Filament\Support\Icons\Heroicon;

class TeamsWithoutManagersWidget extends AdminSection
{
    protected static ?int $sort = 5;

    protected string $view = 'filament.admin.widgets.simple-attention-list';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ($user->isSystemAdmin() || $user->hasOversight());
    }

    protected function sectionData(): array
    {
        $rows = $this->attention()->teamsWithoutManagers();
        $total = $rows->count();

        return [
            'heading' => __('Teams without managers'),
            'icon' => Heroicon::OutlinedUserGroup,
            'iconColor' => Palette::uncertain(),
            'rows' => $rows->take(AdminAttention::PREVIEW_LIMIT)->values(),
            'total' => $total,
            'rowKeyPrefix' => 'team-no-manager',
            'recordUrl' => fn (Team $record): string => MemberResource::getUrl('index', panel: 'teams', tenant: $record),
            'seeAllUrl' => TeamResource::getUrl('index'),
            'seeAllLabel' => __('Browse teams'),
            'previewLimit' => AdminAttention::PREVIEW_LIMIT,
        ];
    }
}
