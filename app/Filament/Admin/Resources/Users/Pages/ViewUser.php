<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Filament\Concerns\AcknowledgesPersonalData;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    use AcknowledgesPersonalData;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        abort_unless($record instanceof User, 404);

        return [
            UserResource::invitationActions($record),
            EditAction::make(),
            RestoreAction::make(),
        ];
    }
}
