<?php

namespace App\Filament\Manage\Resources\NewsPosts\Actions;

use App\Models\NewsPost;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class PublishAction
{
    public static function make(): Action
    {
        return Action::make('publish')
            ->label(__('Publish'))
            ->color(Palette::positive())
            ->icon(Heroicon::Check)
            ->visible(fn (NewsPost $record): bool => is_null($record->deleted_at)
                && is_null($record->published_at)
                && is_null($record->notified_at))
            ->action(function (NewsPost $record): void {
                $record->publishNow();
            });
    }
}
