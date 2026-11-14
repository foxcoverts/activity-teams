<?php

namespace App\Filament\Admin\Resources\Keys\Pages;

use App\Filament\Admin\Resources\Keys\KeyResource;
use App\Filament\Admin\Resources\Locks\LockResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListKeys extends ListRecords
{
    protected static string $resource = KeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('locks')
                ->label(__('Locks'))
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('gray')
                ->url(fn (): string => LockResource::getUrl(panel: 'admin'))
                ->visible(fn (): bool => LockResource::canViewAny()),
            CreateAction::make(),
        ];
    }
}
