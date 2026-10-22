<?php

namespace App\Filament\Manage\Resources\Members\Pages;

use App\Filament\Manage\Actions\SuspensionActions;
use App\Filament\Manage\Concerns\AcknowledgesPersonalData;
use App\Filament\Manage\Resources\Members\MemberResource;
use Filament\Resources\Pages\EditRecord;

class EditMember extends EditRecord
{
    use AcknowledgesPersonalData;

    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SuspensionActions::group($this->getRecord()),
        ];
    }
}
