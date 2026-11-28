<?php

namespace App\Filament\Teams\Resources\MerchandiseOrders\Pages;

use App\Filament\Teams\Resources\MerchandiseOrders\MerchandiseOrderResource;
use Filament\Resources\Pages\ListRecords;

class ListMerchandiseOrders extends ListRecords
{
    protected static string $resource = MerchandiseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
