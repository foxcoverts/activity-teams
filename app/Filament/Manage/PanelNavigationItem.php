<?php

namespace App\Filament\Manage;

use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;

class PanelNavigationItem
{
    public static function make(): NavigationItem
    {
        return NavigationItem::make(static::label())
            ->icon(static::icon())
            ->sort(-1)
            ->visible(function (): bool {
                $user = auth()->user();
                $panel = static::panel();

                return $user instanceof User
                    && $panel instanceof Panel
                    && $user->canAccessPanel($panel)
                    && $user->getDefaultTenant($panel) instanceof Team;
            })
            ->url(function (): ?string {
                $user = auth()->user();
                $panel = static::panel();

                if (! $user instanceof User || ! $panel instanceof Panel) {
                    return null;
                }

                return $panel->getUrl($user->getDefaultTenant($panel));
            });
    }

    public static function icon(): Heroicon|string
    {
        return Heroicon::OutlinedUserGroup;
    }

    public static function label(): string
    {
        return __('Manage');
    }

    protected static function panel(): ?Panel
    {
        return Filament::getPanel('manage');
    }
}
