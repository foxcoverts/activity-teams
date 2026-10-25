<?php

namespace App\Filament\Admin\Resources\KeyRings\Pages;

use App\Filament\Admin\Resources\KeyRings\KeyRingResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditKeyRing extends EditRecord
{
    protected static string $resource = KeyRingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
