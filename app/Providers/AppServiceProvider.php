<?php

namespace App\Providers;

use App\Models\User;
use App\Qualifications\CatalogueCompiler;
use App\Qualifications\QualificationCatalogue;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CatalogueCompiler::class);
        $this->app->singleton(QualificationCatalogue::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        $this->optimizes(
            optimize: 'qualifications:cache',
            clear: 'qualifications:clear',
            key: 'qualifications',
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::TOPBAR_AFTER,
            fn (): View => view('filament.hooks.profile-confirmation-banner'),
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::GLOBAL_SEARCH_AFTER,
            fn (): View => view('filament.hooks.inbox-trigger'),
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            function (): View|HtmlString {
                $user = auth()->user();

                if (! $user instanceof User) {
                    return new HtmlString('');
                }

                $user->ensureFeedToken();
                $user = $user->fresh();

                if (! $user instanceof User) {
                    return new HtmlString('');
                }

                return view('filament.hooks.feed-links', [
                    'atomUrl' => route('news.atom', $user),
                    'rssUrl' => route('news.rss', $user),
                    'bookingsUrl' => route('booking.ics', $user),
                    'rotaUrl' => route('booking.rota.ics', $user),
                ]);
            },
        );
    }
}
