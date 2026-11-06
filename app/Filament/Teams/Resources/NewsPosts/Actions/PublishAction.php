<?php

namespace App\Filament\Teams\Resources\NewsPosts\Actions;

use App\Enums\NewsPostStatus;
use App\Models\NewsPost;
use Filament\Actions\Action;

class PublishAction
{
    public static function make(): Action
    {
        return Action::make('publish')
            ->label(__('Publish'))
            ->color(NewsPostStatus::Published->getColor())
            ->icon(NewsPostStatus::Published->getIcon())
            ->visible(fn (NewsPost $record): bool => is_null($record->deleted_at)
                && is_null($record->published_at)
                && is_null($record->notified_at)
                && auth()->user()->can('update', $record))
            ->action(function (NewsPost $record): void {
                $record->publishNow();
            });
    }
}
