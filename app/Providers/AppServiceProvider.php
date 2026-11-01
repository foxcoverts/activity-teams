<?php

namespace App\Providers;

use App\Qualifications\CatalogueCompiler;
use App\Qualifications\QualificationCatalogue;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
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
    }
}
