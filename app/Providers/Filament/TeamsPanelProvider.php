<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\Dashboard as AdminDashboard;
use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Login;
use App\Filament\Manage\Pages\Dashboard as ManageDashboard;
use App\Filament\Member\Pages\Dashboard as MemberDashboard;
use App\Filament\Member\Pages\Feeds;
use App\Filament\Member\Pages\NotificationSettings;
use App\Filament\Pages\ChangePassword;
use App\Filament\Teams\NavigationGroup;
use App\Filament\Teams\Pages\Dashboard;
use App\Http\Middleware\RememberTenantTeam;
use App\Http\Middleware\RequireProfileConfirmation;
use App\Http\Middleware\ShowPublicBookingSummary;
use App\Models\Team;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use RalphJSmit\Filament\Activitylog\FilamentActivitylog;

class TeamsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('teams')
            ->path('teams')
            ->login(Login::class)
            ->passwordReset()
            ->emailVerification()
            ->colors([
                'primary' => Color::Sky,
            ])
            ->viteTheme('resources/css/filament/theme.css')
            ->plugin(FilamentActivitylog::make())
            ->tenant(Team::class, slugAttribute: 'slug')
            ->tenantMiddleware([
                RememberTenantTeam::class,
            ], isPersistent: true)
            ->navigationGroups(NavigationGroup::class)
            ->discoverResources(in: app_path('Filament/Teams/Resources'), for: 'App\\Filament\\Teams\\Resources')
            ->discoverPages(in: app_path('Filament/Teams/Pages'), for: 'App\\Filament\\Teams\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->userMenuItems([
                EditProfile::userMenuItem(),
                NotificationSettings::userMenuItem(),
                Feeds::userMenuItem(),
                ChangePassword::userMenuItem(),
            ])
            ->navigationItems([
                MemberDashboard::navigationItem(),
                ManageDashboard::navigationItem(),
                AdminDashboard::navigationItem(),
            ])
            ->discoverWidgets(in: app_path('Filament/Teams/Widgets'), for: 'App\\Filament\\Teams\\Widgets')
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
                ShowPublicBookingSummary::class,
                Authenticate::class,
                RequireProfileConfirmation::class,
            ]);
    }
}
