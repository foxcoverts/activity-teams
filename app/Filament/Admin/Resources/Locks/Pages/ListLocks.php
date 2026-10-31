<?php

namespace App\Filament\Admin\Resources\Locks\Pages;

use App\Filament\Admin\Resources\Locks\LockResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLocks extends ListRecords
{
    protected static string $resource = LockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
