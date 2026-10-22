<?php

namespace App\Filament\Manage\Resources\Users\Pages;

use App\Filament\Manage\Concerns\AcknowledgesPersonalData;
use App\Filament\Manage\Resources\Users\UserResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    use AcknowledgesPersonalData;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
