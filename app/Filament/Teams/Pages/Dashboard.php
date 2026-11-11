<?php

namespace App\Filament\Teams\Pages;

use App\Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\Teams\Widgets\AllCaughtUpWidget;
use App\Filament\Teams\Widgets\AssignedTasksWidget;
use App\Filament\Teams\Widgets\LatestNewsWidget;
use App\Filament\Teams\Widgets\NeedsResponseWidget;
use App\Filament\Teams\Widgets\NextLedBookingWidget;
use App\Filament\Teams\Widgets\ThisWeekWidget;
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
    protected static ?string $title = 'Teams';

    protected static string $panel = 'teams';

    protected static ?int $navigationSort = -4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::UserGroup;

    public function getTitle(): string|Htmlable
    {
        $tenant = Filament::getTenant();

        if ($tenant) {
            return __(':Team Overview', ['team' => $tenant->name]); // Custom dynamic title
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

    /**
     * The member dashboard sections, scoped to the active team. The inbox stays on the member dashboard because notices are not team-specific.
     *
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            ThisWeekWidget::class,
            NeedsResponseWidget::class,
            AssignedTasksWidget::class,
            NextLedBookingWidget::class,
            AllCaughtUpWidget::class,
            LatestNewsWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
