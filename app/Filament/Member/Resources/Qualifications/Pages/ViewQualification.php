<?php

namespace App\Filament\Member\Resources\Qualifications\Pages;

use App\Filament\Member\Resources\Qualifications\QualificationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewQualification extends ViewRecord
{
    protected static string $resource = QualificationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
