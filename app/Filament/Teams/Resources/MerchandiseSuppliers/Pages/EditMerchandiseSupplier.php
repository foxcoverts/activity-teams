<?php

namespace App\Filament\Teams\Resources\MerchandiseSuppliers\Pages;

use App\Filament\Teams\Resources\MerchandiseSuppliers\MerchandiseSupplierResource;
use App\Models\MerchandiseSupplier;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMerchandiseSupplier extends EditRecord
{
    protected static string $resource = MerchandiseSupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->visible(fn (MerchandiseSupplier $record): bool => auth()->user()?->can('delete', $record) ?? false),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
