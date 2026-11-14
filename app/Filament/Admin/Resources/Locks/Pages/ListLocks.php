<?php

namespace App\Filament\Admin\Resources\Locks\Pages;

use App\Filament\Admin\Resources\Keys\KeyResource;
use App\Filament\Admin\Resources\Locks\LockResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListLocks extends ListRecords
{
    protected static string $resource = LockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('keys')
                ->label(__('Keys'))
                ->icon(Heroicon::OutlinedKey)
                ->color('gray')
                ->url(fn (): string => KeyResource::getUrl(panel: 'admin'))
                ->visible(fn (): bool => KeyResource::canViewAny()),
            CreateAction::make(),
        ];
    }
}
