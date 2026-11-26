<?php

namespace App\Filament\Teams\Resources\MerchandiseItems\Pages;

use App\Filament\Teams\Resources\MerchandiseItems\MerchandiseItemResource;
use App\Filament\Teams\Resources\MerchandiseItems\Pages\Concerns\SyncsMerchandiseVariantOptions;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMerchandiseItem extends EditRecord
{
    use SyncsMerchandiseVariantOptions;

    protected static string $resource = MerchandiseItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function beforeValidate(): void
    {
        $this->captureVariantOptionSelectionsForSync();
    }

    protected function afterSave(): void
    {
        $this->syncVariantOptionSelections();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
