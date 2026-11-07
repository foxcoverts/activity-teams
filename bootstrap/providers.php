<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\MemberPanelProvider;
use App\Providers\Filament\TeamsPanelProvider;
use App\Providers\Filament\TimelineProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    MemberPanelProvider::class,
    TeamsPanelProvider::class,
    TimelineProvider::class,
];
