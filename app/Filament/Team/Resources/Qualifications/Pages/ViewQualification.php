<?php

namespace App\Filament\Team\Resources\Qualifications\Pages;

use App\Filament\Team\Resources\Qualifications\QualificationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewQualification extends ViewRecord
{
    protected static string $resource = QualificationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
