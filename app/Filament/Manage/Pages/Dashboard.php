<?php

namespace App\Filament\Manage\Pages;

use App\Filament\Pages\Dashboard as BaseDashboard;
use App\Models\Team;
use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Manage Team';

    protected static string $panel = 'manage';

    protected static ?int $navigationSort = -4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::UserGroup;

    protected static function isNavigationVisible(): bool|Closure
    {
        return function (): bool {
            $user = auth()->user();
            $panel = static::panel();

            return $user instanceof User
                && $panel instanceof Panel
                && $user->canAccessPanel($panel)
                && $user->getDefaultTenant($panel) instanceof Team;
        };
    }

    public static function getNavigationUrl(): string
    {
        $user = auth()->user();
        $panel = static::panel();

        if (! $user instanceof User || ! $panel instanceof Panel) {
            return '';
        }

        return $panel->getUrl($user->getDefaultTenant($panel)) ?? '';
    }
}
