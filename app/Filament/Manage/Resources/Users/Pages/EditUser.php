<?php

namespace App\Filament\Manage\Resources\Users\Pages;

use App\Actions\DeleteUserAction;
use App\Filament\Manage\Actions\SuspensionActions;
use App\Filament\Manage\Concerns\AcknowledgesPersonalData;
use App\Filament\Manage\Resources\Users\UserResource;
use App\Models\User;
use App\Support\Palette;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditUser extends EditRecord
{
    use AcknowledgesPersonalData;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->using(function (User $record): void {
                    $actor = auth()->user();

                    if ($actor instanceof User) {
                        app(DeleteUserAction::class)->delete($actor, $record);
                    }
                }),
            RestoreAction::make(),
            ActionGroup::make(SuspensionActions::list($this->getRecord()))
                ->label(__('More actions'))
                ->icon(Heroicon::EllipsisVertical)
                ->color(Palette::neutral())
                ->button(),
        ];
    }
}
