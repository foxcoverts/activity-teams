<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\ManagePanelProvider;
use App\Providers\Filament\TeamPanelProvider;
use App\Providers\Filament\TimelineProvider;

return [
    AppServiceProvider::class,
    ManagePanelProvider::class,
    TeamPanelProvider::class,
    TimelineProvider::class,
];
