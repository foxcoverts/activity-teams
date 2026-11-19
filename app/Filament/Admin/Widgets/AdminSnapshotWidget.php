<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Activities\ActivityResource;
use App\Filament\Admin\Resources\Audiences\AudienceResource;
use App\Filament\Admin\Resources\Keys\KeyResource;
use App\Filament\Admin\Resources\Teams\TeamResource;
use App\Filament\Admin\Resources\Venues\VenueResource;
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
        $snapshot = $this->attention()->snapshot();

        $items = [
            ['label' => __('Teams'), 'value' => $snapshot['teams'], 'href' => TeamResource::getUrl('index')],
            ['label' => __('Activities'), 'value' => $snapshot['activities'], 'href' => ActivityResource::getUrl('index')],
            ['label' => __('Venues'), 'value' => $snapshot['venues'], 'href' => VenueResource::getUrl('index')],
            ['label' => __('Audiences'), 'value' => $snapshot['audiences'], 'href' => AudienceResource::getUrl('index')],
            ['label' => __('Keys'), 'value' => $snapshot['keys'], 'href' => KeyResource::getUrl('index')],
        ];

        return [
            'heading' => __('At a glance'),
            'icon' => Heroicon::OutlinedChartBar,
            'iconColor' => Palette::neutral(),
            'items' => $items,
        ];
    }
}
