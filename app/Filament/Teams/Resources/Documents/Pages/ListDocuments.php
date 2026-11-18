<?php

namespace App\Filament\Teams\Resources\Documents\Pages;

use App\Filament\Teams\Resources\Documents\DocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    protected string $view = 'filament.resources.documents.list-documents';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
