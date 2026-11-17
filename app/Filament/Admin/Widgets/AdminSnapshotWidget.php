<?php

namespace App\Filament\Admin\Widgets;

use App\Models\User;
use App\Support\Palette;
use Filament\Support\Icons\Heroicon;

class AdminSnapshotWidget extends AdminSection
{
    protected static ?int $sort = 1;

    protected string $view = 'filament.admin.widgets.snapshot';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ($user->isSystemAdmin() || $user->hasOversight());
    }

    protected function sectionData(): array
    {
        return [
            'heading' => __('At a glance'),
            'icon' => Heroicon::OutlinedChartBar,
            'iconColor' => Palette::neutral(),
            'snapshot' => $this->attention()->snapshot(),
        ];
    }
}
