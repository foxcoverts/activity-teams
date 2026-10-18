<?php

namespace App\Filament\Team\Resources\Qualifications\Pages;

use App\Filament\Team\Resources\Qualifications\QualificationResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListQualifications extends ListRecords
{
    protected static string $resource = QualificationResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('These are the qualifications recorded for you. A team manager can add or change them.');
    }
}
