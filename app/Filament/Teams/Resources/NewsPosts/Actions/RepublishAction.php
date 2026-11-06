<?php

namespace App\Filament\Teams\Resources\NewsPosts\Actions;

use App\Enums\NewsPostStatus;
use App\Models\NewsPost;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;

class RepublishAction
{
    public static function make(): Action
    {
        return Action::make('republish')
            ->label(__('Republish'))
            ->color(NewsPostStatus::Published->getColor())
            ->icon(NewsPostStatus::Published->getIcon())
            ->visible(fn (NewsPost $record): bool => is_null($record->deleted_at)
                && is_null($record->published_at)
                && filled($record->notified_at)
                && auth()->user()->can('update', $record))
            ->schema([
                Toggle::make('send_update')
                    ->label(__('Send an update to members'))
                    ->default(false),
            ])
            ->modalSubmitActionLabel(__('Republish'))
            ->action(function (NewsPost $record, array $data): void {
                $record->republish((bool) ($data['send_update'] ?? false));
            });
    }
}
