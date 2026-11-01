<?php

namespace App\Filament\Member\Resources\Documents\Pages;

use App\Filament\Member\Resources\Documents\DocumentResource;
use Filament\Resources\Pages\ListRecords;

class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;
}
