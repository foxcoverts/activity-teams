<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\User;
use App\Support\AdminAttention;
use App\Support\Palette;
use Filament\Support\Icons\Heroicon;

class PendingSiteInvitesWidget extends AdminSection
{
    protected static ?int $sort = 4;

    protected string $view = 'filament.admin.widgets.pending-site-invites';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ($user->isSystemAdmin() || $user->hasOversight());
    }

    protected function sectionData(): array
    {
        $rows = $this->attention()->pendingSiteInvites();
        $total = $rows->count();

        return [
            'heading' => __('Pending site-access invitations'),
            'icon' => Heroicon::OutlinedEnvelope,
            'iconColor' => Palette::uncertain(),
            'rows' => $rows->take(AdminAttention::PREVIEW_LIMIT)->values(),
            'total' => $total,
            'seeAllUrl' => UserResource::getUrl('index', [
                'tableFilters' => [
                    'not_accepted' => ['isActive' => true],
                ],
            ]),
            'seeAllLabel' => __('See all not-yet-accepted accounts'),
            'previewLimit' => AdminAttention::PREVIEW_LIMIT,
        ];
    }
}
