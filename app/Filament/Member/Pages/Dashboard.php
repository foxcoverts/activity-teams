<?php

namespace App\Filament\Member\Pages;

use App\Filament\Member\Widgets\AllCaughtUpWidget;
use App\Filament\Member\Widgets\LatestNewsWidget;
use App\Filament\Member\Widgets\MyTeamsWidget;
use App\Filament\Member\Widgets\NeedsResponseWidget;
use App\Filament\Member\Widgets\NextLedBookingWidget;
use App\Filament\Member\Widgets\ThisWeekWidget;
use App\Filament\Member\Widgets\UnreadInboxWidget;
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

    /**
     * The fixed section order. Each widget hides itself when it has nothing to show.
     *
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            UnreadInboxWidget::class,
            ThisWeekWidget::class,
            NeedsResponseWidget::class,
            NextLedBookingWidget::class,
            AllCaughtUpWidget::class,
            MyTeamsWidget::class,
            LatestNewsWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
