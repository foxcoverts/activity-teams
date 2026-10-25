<?php

namespace App\Filament\Admin\Resources\Keys\Pages;

use App\Filament\Admin\Resources\Keys\KeyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKeys extends ListRecords
{
    protected static string $resource = KeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
