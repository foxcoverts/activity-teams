<?php

namespace App\Filament\Manage\Pages;

use App\Filament\Pages\Dashboard as BaseDashboard;
use App\Models\Team;
use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Manage';

    protected static string $panel = 'manage';

    protected static ?int $navigationSort = -3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Cog6Tooth;

    public function getTitle(): string|Htmlable
    {
        $tenant = Filament::getTenant();

        if ($tenant) {
            return __('Manage :Team', ['team' => $tenant->name]); // Custom dynamic title
        }

        return __('filament-panels::pages/dashboard.title');
    }

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
