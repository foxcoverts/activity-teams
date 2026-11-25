<?php

namespace App\Filament\Teams\Resources\MerchandiseShops\Pages;

use App\Filament\Teams\Resources\MerchandiseShops\MerchandiseShopResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewMerchandiseShop extends ViewRecord
{
    protected static string $resource = MerchandiseShopResource::class;

    protected string $view = 'filament.resources.merchandise-shops.view-merchandise-shop';

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
