<?php

namespace App\Filament\Admin\Resources\Locks\Pages;

use App\Filament\Admin\Resources\Locks\LockResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditLock extends EditRecord
{
    protected static string $resource = LockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    public function getRelationManagers(): array
    {
        return [];
    }
}
