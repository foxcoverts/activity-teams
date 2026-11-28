<?php

namespace App\Filament\Teams\Resources\MerchandiseSuppliers\Pages;

use App\Filament\Teams\Resources\MerchandiseSuppliers\MerchandiseSupplierResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateMerchandiseSupplier extends CreateRecord
{
    protected static string $resource = MerchandiseSupplierResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['team_id'] = Filament::getTenant()?->getKey();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
