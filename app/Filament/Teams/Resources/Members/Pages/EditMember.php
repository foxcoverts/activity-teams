<?php

namespace App\Filament\Teams\Resources\Members\Pages;

use App\Filament\Concerns\AcknowledgesPersonalData;
use App\Filament\Teams\Actions\SuspensionActions;
use App\Filament\Teams\Resources\Members\MemberResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditMember extends EditRecord
{
    use AcknowledgesPersonalData;

    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make($this->getRecord()),
            ActionGroup::make([
                MemberResource::changeRoleAction($this->getRecord()),
                ...SuspensionActions::list($this->getRecord()),
            ])
                ->label(__('More actions'))
                ->icon(Heroicon::EllipsisVertical)
                ->color('gray')
                ->button(),
        ];
    }
}
