<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\ManagePanelProvider;
use App\Providers\Filament\MemberPanelProvider;
use App\Providers\Filament\TimelineProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    ManagePanelProvider::class,
    MemberPanelProvider::class,
    TimelineProvider::class,
];
