<?php

namespace App\Filament\Teams\Resources\MerchandiseShops\Pages;

use App\Filament\Teams\Resources\MerchandiseShops\MerchandiseShopResource;
use Filament\Resources\Pages\ListRecords;

class ListMerchandiseShops extends ListRecords
{
    protected static string $resource = MerchandiseShopResource::class;

    protected string $view = 'filament.resources.merchandise-shops.list-merchandise-shops';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
