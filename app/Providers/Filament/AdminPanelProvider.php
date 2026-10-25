<?php

namespace App\Providers\Filament;

use App\Filament\Admin\NavigationGroup;
use App\Filament\Admin\PanelNavigationItem as AdminPanelNavigationItem;
use App\Filament\Auth\EditProfile;
use App\Filament\Manage\PanelNavigationItem as ManagePanelNavigationItem;
use App\Filament\Member\PanelNavigationItem as MemberPanelNavigationItem;
use App\Filament\Pages\ChangePassword;
use App\Http\Middleware\RequireProfileConfirmation;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsIconAlias;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use RalphJSmit\Filament\Activitylog\FilamentActivitylog;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset()
            ->emailVerification()
            ->colors([
                'primary' => Color::Sky,
            ])
            ->icons([
                PanelsIconAlias::PAGES_DASHBOARD_NAVIGATION_ITEM => AdminPanelNavigationItem::icon(),
            ])
            ->viteTheme('resources/css/filament/theme.css')
            ->plugin(FilamentActivitylog::make())
            ->navigationGroups(NavigationGroup::class)
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->userMenuItems([
                EditProfile::userMenuItem(),
                ChangePassword::userMenuItem(),
            ])
            ->navigationItems([
                MemberPanelNavigationItem::make(),
                ManagePanelNavigationItem::make(),
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                RequireProfileConfirmation::class,
            ]);
    }
}
