<?php

namespace App\Filament\Manage\Resources\Members\Pages;

use App\Filament\Manage\Concerns\AcknowledgesPersonalData;
use App\Filament\Manage\Resources\Members\MemberResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMember extends ViewRecord
{
    use AcknowledgesPersonalData;

    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
