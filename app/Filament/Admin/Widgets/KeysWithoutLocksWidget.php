<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Keys\KeyResource;
use App\Models\User;
use App\Support\AdminAttention;
use App\Support\Palette;
use Filament\Support\Icons\Heroicon;

class KeysWithoutLocksWidget extends AdminSection
{
    protected static ?int $sort = 9;

    protected string $view = 'filament.admin.widgets.simple-attention-list';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isSystemAdmin();
    }

    protected function sectionData(): array
    {
        $rows = $this->attention()->keysWithoutLocks();
        $total = $rows->count();

        return [
            'heading' => __('Keys with no locks'),
            'icon' => Heroicon::OutlinedKey,
            'iconColor' => Palette::uncertain(),
            'rows' => $rows->take(AdminAttention::PREVIEW_LIMIT)->values(),
            'total' => $total,
            'rowKeyPrefix' => 'key-no-locks',
            'recordUrl' => fn ($record): string => KeyResource::getUrl('view', ['record' => $record]),
            'seeAllUrl' => KeyResource::getUrl('index', [
                'filters' => [
                    'without_locks' => ['isActive' => true],
                ],
            ]),
            'seeAllLabel' => __('See all keys with no locks'),
            'previewLimit' => AdminAttention::PREVIEW_LIMIT,
        ];
    }
}
