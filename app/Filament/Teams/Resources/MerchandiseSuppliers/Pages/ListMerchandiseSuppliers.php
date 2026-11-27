<?php

namespace App\Filament\Teams\Resources\MerchandiseSuppliers\Pages;

use App\Filament\Teams\Resources\MerchandiseSuppliers\MerchandiseSupplierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMerchandiseSuppliers extends ListRecords
{
    protected static string $resource = MerchandiseSupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
