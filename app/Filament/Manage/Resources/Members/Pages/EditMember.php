<?php

namespace App\Filament\Manage\Resources\Members\Pages;

use App\Filament\Concerns\AcknowledgesPersonalData;
use App\Filament\Manage\Actions\SuspensionActions;
use App\Filament\Manage\Resources\Members\MemberResource;
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
                MemberResource::changeRoleAction($this->getRecord())
                    ->icon(Heroicon::User),
                ...SuspensionActions::list($this->getRecord()),
            ])
                ->label(__('More actions'))
                ->icon(Heroicon::EllipsisVertical)
                ->color('gray')
                ->button(),
        ];
    }
}
