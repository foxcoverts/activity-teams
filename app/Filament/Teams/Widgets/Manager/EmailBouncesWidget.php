<?php

namespace App\Filament\Teams\Widgets\Manager;

use App\Filament\Teams\Resources\Members\MemberResource;
use App\Support\ManagerAttention;
use App\Support\Palette;
use Filament\Support\Icons\Heroicon;

class EmailBouncesWidget extends ManagerSection
{
    protected static ?int $sort = 5;

    protected string $view = 'filament.teams.widgets.manager.email-bounces';

    protected function sectionData(): array
    {
        $rows = $this->attention()->bouncedMembers();
        $total = $rows->count();

        return [
            'heading' => __('Email bounces'),
            'icon' => Heroicon::OutlinedExclamationTriangle,
            'iconColor' => Palette::destructive(),
            'rows' => $rows->take(ManagerAttention::PREVIEW_LIMIT)->values(),
            'total' => $total,
            'seeAllUrl' => MemberResource::getUrl('index', [
                'tableFilters' => [
                    'email_bounced' => ['isActive' => true],
                ],
            ]),
            'seeAllLabel' => __('See all bounced members'),
            'previewLimit' => ManagerAttention::PREVIEW_LIMIT,
        ];
    }
}
