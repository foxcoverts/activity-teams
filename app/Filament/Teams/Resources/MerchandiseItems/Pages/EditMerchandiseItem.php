<?php

namespace App\Filament\Teams\Resources\MerchandiseItems\Pages;

use App\Filament\Teams\Resources\MerchandiseItems\MerchandiseItemResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMerchandiseItem extends EditRecord
{
    protected static string $resource = MerchandiseItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
