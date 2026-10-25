<?php

namespace App\Filament\Member;

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
            ->url(fn (): ?string => static::panel()?->getUrl());
    }

    public static function icon(): Heroicon|string
    {
        return Heroicon::OutlinedHome;
    }

    public static function label(): string
    {
        return __('Members');
    }

    protected static function panel(): ?Panel
    {
        return Filament::getPanel('member');
    }
}
