<?php

namespace App\Filament\Teams\Resources\MerchandiseItems\Pages;

use App\Filament\Teams\Resources\MerchandiseItems\MerchandiseItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMerchandiseItems extends ListRecords
{
    protected static string $resource = MerchandiseItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
