<?php

namespace App\Filament\Teams\Resources\MerchandiseShops\Pages;

use App\Filament\Teams\Resources\MerchandiseShops\MerchandiseShopResource;
use Filament\Resources\Pages\ViewRecord;

class ViewMerchandiseShop extends ViewRecord
{
    protected static string $resource = MerchandiseShopResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
