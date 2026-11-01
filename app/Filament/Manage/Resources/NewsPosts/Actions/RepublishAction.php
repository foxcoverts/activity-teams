<?php

namespace App\Filament\Manage\Resources\NewsPosts\Actions;

use App\Models\NewsPost;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Support\Icons\Heroicon;

class RepublishAction
{
    public static function make(): Action
    {
        return Action::make('republish')
            ->label(__('Republish'))
            ->color(Palette::positive())
            ->icon(Heroicon::ArrowPath)
            ->visible(fn (NewsPost $record): bool => is_null($record->deleted_at)
                && is_null($record->published_at)
                && filled($record->notified_at))
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
