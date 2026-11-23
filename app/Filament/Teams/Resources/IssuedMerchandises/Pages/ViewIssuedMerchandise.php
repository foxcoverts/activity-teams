<?php

namespace App\Filament\Teams\Resources\IssuedMerchandises\Pages;

use App\Filament\Teams\Resources\IssuedMerchandises\IssuedMerchandiseResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewIssuedMerchandise extends ViewRecord
{
    protected static string $resource = IssuedMerchandiseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
