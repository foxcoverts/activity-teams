<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\Dashboard as AdminDashboard;
use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Login;
use App\Filament\Auth\Register;
use App\Filament\Manage\Pages\Dashboard as ManageDashboard;
use App\Filament\Member\NavigationGroup;
use App\Filament\Member\Pages\Dashboard;
use App\Filament\Member\Pages\Feeds;
use App\Filament\Member\Pages\NotificationSettings;
use App\Filament\Pages\ChangePassword;
use App\Http\Middleware\RequireProfileConfirmation;
use App\Http\Middleware\ShowPublicBookingSummary;
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

class MemberPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('member')
            ->path('')
            ->login(Login::class)
            ->registration(Register::class)
            ->passwordReset()
            ->emailVerification()
            ->profile(EditProfile::class, isSimple: false)
            ->colors([
                'primary' => Color::Sky,
            ])
            ->viteTheme('resources/css/filament/theme.css')
            ->plugin(FilamentActivitylog::make())
            ->navigationGroups(NavigationGroup::class)
            ->discoverResources(in: app_path('Filament/Member/Resources'), for: 'App\\Filament\\Member\\Resources')
            ->discoverPages(in: app_path('Filament/Member/Pages'), for: 'App\\Filament\\Member\\Pages')
            ->pages([
                Dashboard::class,
                ChangePassword::class,
            ])
            ->userMenuItems([
                NotificationSettings::userMenuItem(),
                Feeds::userMenuItem(),
                ChangePassword::userMenuItem(),
            ])
            ->navigationItems([
                ManageDashboard::navigationItem(),
                AdminDashboard::navigationItem(),
            ])
            ->discoverWidgets(in: app_path('Filament/Member/Widgets'), for: 'App\\Filament\\Member\\Widgets')
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
