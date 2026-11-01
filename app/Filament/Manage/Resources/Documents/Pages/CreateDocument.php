<?php

namespace App\Filament\Manage\Resources\Documents\Pages;

use App\Filament\Manage\Resources\Documents\DocumentResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uploader_id'] = auth()->id();
        $data['team_id'] = Filament::getTenant()?->getKey();

        return $data;
    }
}
