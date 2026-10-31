<?php

namespace App\Filament\Admin\Resources\Locks\Pages;

use App\Filament\Admin\Resources\Locks\LockResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLock extends ViewRecord
{
    protected static string $resource = LockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
