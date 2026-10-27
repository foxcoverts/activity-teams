<?php

namespace App\Providers\Filament;

use App\Filament\Admin\PanelNavigationItem as AdminPanelNavigationItem;
use App\Filament\Auth\EditProfile;
use App\Filament\Manage\NavigationGroup;
use App\Filament\Manage\PanelNavigationItem as ManagePanelNavigationItem;
use App\Filament\Member\Pages\NotificationSettings;
use App\Filament\Member\PanelNavigationItem as MemberPanelNavigationItem;
use App\Filament\Pages\ChangePassword;
use App\Http\Middleware\RememberManageTeam;
use App\Http\Middleware\RequireProfileConfirmation;
use App\Models\Team;
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

class ManagePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('manage')
            ->path('manage')
            ->login()
            ->passwordReset()
            ->emailVerification()
            ->colors([
                'primary' => Color::Sky,
            ])
            ->icons([
                PanelsIconAlias::PAGES_DASHBOARD_NAVIGATION_ITEM => ManagePanelNavigationItem::icon(),
            ])
            ->viteTheme('resources/css/filament/theme.css')
            ->plugin(FilamentActivitylog::make())
            ->tenant(Team::class, slugAttribute: 'slug')
            ->tenantMiddleware([
                RememberManageTeam::class,
            ], isPersistent: true)
            ->navigationGroups(NavigationGroup::class)
            ->discoverResources(in: app_path('Filament/Manage/Resources'), for: 'App\\Filament\\Manage\\Resources')
            ->discoverPages(in: app_path('Filament/Manage/Pages'), for: 'App\\Filament\\Manage\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->userMenuItems([
                EditProfile::userMenuItem(),
                NotificationSettings::userMenuItem(),
                ChangePassword::userMenuItem(),
            ])
            ->navigationItems([
                MemberPanelNavigationItem::make(),
                AdminPanelNavigationItem::make(),
            ])
            ->discoverWidgets(in: app_path('Filament/Manage/Widgets'), for: 'App\\Filament\\Manage\\Widgets')
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
