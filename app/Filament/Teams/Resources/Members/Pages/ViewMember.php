<?php

namespace App\Filament\Teams\Resources\Members\Pages;

use App\Filament\Concerns\AcknowledgesPersonalData;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMember extends ViewRecord
{
    use AcknowledgesPersonalData;

    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        abort_unless($record instanceof User, 404);

        return [
            MemberResource::invitationActions($record),
            EditAction::make(),
            MemberResource::changeRoleAction($record),
            MemberResource::removeFromTeamAction($record),
        ];
    }
}
