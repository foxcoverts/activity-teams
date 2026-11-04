<?php

namespace App\Filament\Teams\Resources\Documents\Pages;

use App\Filament\Documents\DownloadDocumentAction;
use App\Filament\Teams\Resources\Documents\DocumentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDocument extends ViewRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DownloadDocumentAction::make(),
        ];
    }
}
