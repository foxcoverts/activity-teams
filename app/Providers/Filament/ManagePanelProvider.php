<?php

namespace App\Providers\Filament;

use App\Filament\Auth\EditProfile;
use App\Filament\Manage\NavigationGroup;
use App\Filament\Pages\ChangePassword;
use App\Http\Middleware\RememberManageTeam;
use App\Http\Middleware\RequireProfileConfirmation;
use App\Models\Team;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
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
            ->viteTheme('resources/css/filament/theme.css')
            ->plugin(FilamentActivitylog::make())
            ->tenant(Team::class)
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
                ChangePassword::userMenuItem(),
            ])
            ->navigationItems([
                NavigationItem::make('Members')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->sort(-1)
                    ->url(fn (): ?string => Filament::getPanel('member')?->getUrl()),
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
