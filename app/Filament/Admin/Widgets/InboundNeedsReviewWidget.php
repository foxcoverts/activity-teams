<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\InboundMails\InboundMailResource;
use App\Models\User;
use App\Support\AdminAttention;
use App\Support\Palette;
use Filament\Support\Icons\Heroicon;

class InboundNeedsReviewWidget extends AdminSection
{
    protected static ?int $sort = 3;

    protected string $view = 'filament.admin.widgets.inbound-needs-review';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSystemAdmin();
    }

    protected function sectionData(): array
    {
        $rows = $this->attention()->inboundNeedingReview();
        $total = $rows->count();

        return [
            'heading' => __('Inbound mail needing review'),
            'icon' => Heroicon::OutlinedInboxArrowDown,
            'iconColor' => Palette::uncertain(),
            'rows' => $rows->take(AdminAttention::PREVIEW_LIMIT)->values(),
            'total' => $total,
            'seeAllUrl' => InboundMailResource::getUrl('index', [
                'filters' => [
                    'needs_review' => [
                        'isActive' => true,
                        'value' => true,
                    ],
                ],
            ]),
            'seeAllLabel' => __('See all inbound mail needing review'),
            'previewLimit' => AdminAttention::PREVIEW_LIMIT,
        ];
    }
}
