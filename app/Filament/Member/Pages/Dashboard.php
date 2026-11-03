<?php

namespace App\Filament\Member\Pages;

use App\Filament\Pages\Dashboard as BaseDashboard;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    protected static string $panel = 'member';

    protected static string $routePath = 'dashboard';

    protected static ?string $title = 'Dashboard';

    protected static ?int $navigationSort = -5;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Home;

    public static function getNavigationUrl(): string
    {
        return static::getUrl(panel: static::$panel);
    }
}
