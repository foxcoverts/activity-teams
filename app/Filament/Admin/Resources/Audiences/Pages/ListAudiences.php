<?php

namespace App\Filament\Admin\Resources\Audiences\Pages;

use App\Filament\Admin\Resources\Audiences\AudienceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAudiences extends ListRecords
{
    protected static string $resource = AudienceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
