<?php

namespace App\Filament\Pages;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Panel;

class Dashboard extends BaseDashboard
{
    protected static string $panel = 'default';

    public static function navigationItem(): NavigationItem
    {
        return NavigationItem::make(static::getNavigationLabel())
            ->key(static::class)
            ->group(static::getNavigationGroup())
            ->parentItem(static::getNavigationParentItem())
            ->icon(static::getNavigationIcon())
            ->activeIcon(static::getActiveNavigationIcon())
            ->sort(static::getNavigationSort())
            ->badge(static::getNavigationBadge(), color: static::getNavigationBadgeColor())
            ->badgeTooltip(static::getNavigationBadgeTooltip())
            ->url(fn () => static::getNavigationUrl())
            ->visible(static::isNavigationVisible());
    }

    public static function getNavigationUrl(): string
    {
        return static::panel()?->getUrl() ?? '';
    }

    protected static function panel(): ?Panel
    {
        return Filament::getPanel(static::$panel);
    }

    protected static function isNavigationVisible(): bool|Closure
    {
        return function (): bool {
            $user = auth()->user();
            $panel = static::panel();

            return $user instanceof User
                && $panel instanceof Panel
                && $user->canAccessPanel($panel);
        };
    }
}
