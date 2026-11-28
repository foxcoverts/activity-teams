<?php

namespace App\Filament\Teams\Resources\MerchandiseItems\Pages;

use App\Filament\Teams\Resources\MerchandiseItems\MerchandiseItemResource;
use App\Filament\Teams\Resources\MerchandiseItems\Pages\Concerns\SyncsMerchandiseVariantOptions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateMerchandiseItem extends CreateRecord
{
    use SyncsMerchandiseVariantOptions;

    protected static string $resource = MerchandiseItemResource::class;

    protected function beforeValidate(): void
    {
        $this->captureVariantOptionSelectionsForSync();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['team_id'] = Filament::getTenant()?->getKey();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->syncVariantOptionSelections();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
