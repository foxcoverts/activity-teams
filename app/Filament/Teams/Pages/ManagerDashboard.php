<?php

namespace App\Filament\Teams\Pages;

use App\Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\Teams\Widgets\Manager\EmailBouncesWidget;
use App\Filament\Teams\Widgets\Manager\ManagerAllCaughtUpWidget;
use App\Filament\Teams\Widgets\Manager\NeedsLeadWidget;
use App\Filament\Teams\Widgets\Manager\NeedsStaffingWidget;
use App\Filament\Teams\Widgets\Manager\ReadyToConfirmWidget;
use App\Models\Team;
use App\Models\User;
use App\Support\ManagerAttention;
use BackedEnum;
use Closure;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ManagerDashboard extends BaseDashboard
{
    protected static ?string $title = 'Manager';

    protected static ?string $navigationLabel = 'Manager';

    protected static string $routePath = 'manager';

    protected static string $panel = 'teams';

    protected static ?int $navigationSort = -3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ClipboardDocumentList;

    public function getTitle(): string|Htmlable
    {
        $tenant = Filament::getTenant();

        if ($tenant instanceof Team) {
            return __('Manager');
        }

        return __('filament-panels::pages/dashboard.title');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $team = Filament::getTenant();

        return $user instanceof User
            && $team instanceof Team
            && ($user->manages($team) || $user->isSystemAdmin());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    protected static function isNavigationVisible(): bool|Closure
    {
        return function (): bool {
            return static::canAccess();
        };
    }

    public static function getNavigationUrl(): string
    {
        $team = Filament::getTenant();

        if (! $team instanceof Team || ! static::canAccess()) {
            return '';
        }

        return static::getUrl(tenant: $team);
    }

    public static function getNavigationBadge(): ?string
    {
        $team = Filament::getTenant();

        if (! $team instanceof Team || ! static::canAccess()) {
            return null;
        }

        $user = auth()->user();
        assert($user instanceof User);

        $total = ManagerAttention::for($team, $user)->summary()['total'];

        return $total > 0 ? (string) $total : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $team = Filament::getTenant();

        if (! $team instanceof Team || ! static::canAccess()) {
            return null;
        }

        $user = auth()->user();
        assert($user instanceof User);

        return ManagerAttention::for($team, $user)->summary()['severity']?->filamentColor();
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            ManagerAllCaughtUpWidget::class,
            NeedsLeadWidget::class,
            ReadyToConfirmWidget::class,
            NeedsStaffingWidget::class,
            EmailBouncesWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}
