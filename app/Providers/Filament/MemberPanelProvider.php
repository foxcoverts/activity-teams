<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Register;
use App\Http\Middleware\ShowPublicBookingSummary;
use App\Models\User;
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

class MemberPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('member')
            ->path('')
            ->brandName('Members')
            ->login()
            ->registration(Register::class)
            ->passwordReset()
            ->emailVerification()
            ->profile()
            ->colors([
                'primary' => Color::Sky,
            ])
            ->viteTheme('resources/css/filament/theme.css')
            ->plugin(FilamentActivitylog::make())
            ->discoverResources(in: app_path('Filament/Team/Resources'), for: 'App\\Filament\\Team\\Resources')
            ->discoverPages(in: app_path('Filament/Team/Pages'), for: 'App\\Filament\\Team\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->navigationItems([
                NavigationItem::make('Manage')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->sort(-1)
                    ->visible(function (): bool {
                        $user = auth()->user();
                        $managePanel = Filament::getPanel('manage');

                        return $user instanceof User
                            && $managePanel instanceof Panel
                            && $user->canAccessPanel($managePanel);
                    })
                    ->url(function (): ?string {
                        $user = auth()->user();
                        $managePanel = Filament::getPanel('manage');

                        if (! $user instanceof User || ! $managePanel instanceof Panel) {
                            return null;
                        }

                        return $managePanel->getUrl($user->getDefaultTenant($managePanel));
                    }),
            ])
            ->discoverWidgets(in: app_path('Filament/Team/Widgets'), for: 'App\\Filament\\Team\\Widgets')
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
            ]);
    }
}
