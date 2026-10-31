<?php

namespace App\Filament\Admin\Resources\Audiences\Pages;

use App\Filament\Admin\Resources\Audiences\AudienceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAudience extends ViewRecord
{
    protected static string $resource = AudienceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
