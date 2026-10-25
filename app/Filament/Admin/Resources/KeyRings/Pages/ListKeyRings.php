<?php

namespace App\Filament\Admin\Resources\KeyRings\Pages;

use App\Filament\Admin\Resources\KeyRings\KeyRingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKeyRings extends ListRecords
{
    protected static string $resource = KeyRingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
