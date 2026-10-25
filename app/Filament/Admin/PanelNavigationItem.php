<?php

namespace App\Filament\Admin;

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
                    && $user->canAccessPanel($panel);
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
        return Heroicon::OutlinedCog6Tooth;
    }

    public static function label(): string
    {
        return __('Admin');
    }

    protected static function panel(): ?Panel
    {
        return Filament::getPanel('admin');
    }
}
