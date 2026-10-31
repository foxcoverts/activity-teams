<?php

namespace App\Filament\Admin\Resources\InboundMails\Pages;

use App\Filament\Admin\Resources\InboundMails\InboundMailResource;
use App\Models\InboundMail;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewInboundMail extends ViewRecord
{
    protected static string $resource = InboundMailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markReviewed')
                ->label(__('Mark reviewed'))
                ->icon(Heroicon::OutlinedCheck)
                ->visible(fn (): bool => $this->getRecord()->reviewed_at === null)
                ->action(function (): void {
                    /** @var InboundMail $record */
                    $record = $this->getRecord();
                    $record->markReviewed()->save();

                    Notification::make()
                        ->title(__('Marked reviewed'))
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
