<?php

namespace App\Filament\Teams\Resources\MerchandiseSuppliers\Pages;

use App\Filament\Teams\Resources\MerchandiseSuppliers\MerchandiseSupplierResource;
use App\Models\MerchandiseSupplier;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMerchandiseSupplier extends ViewRecord
{
    protected static string $resource = MerchandiseSupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make()
                ->visible(fn (MerchandiseSupplier $record): bool => auth()->user()?->can('delete', $record) ?? false),
        ];
    }
}
