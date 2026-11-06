<?php

namespace App\Filament\Teams\Resources\NewsPosts\Actions;

use App\Models\NewsPost;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class UnpublishAction
{
    public static function make(): Action
    {
        return Action::make('unpublish')
            ->label(__('Unpublish'))
            ->color(Palette::uncertain())
            ->icon(Heroicon::ArrowUturnLeft)
            ->visible(fn (NewsPost $record): bool => is_null($record->deleted_at)
                && filled($record->published_at)
                && auth()->user()->can('update', $record))
            ->action(function (NewsPost $record): void {
                $record->unpublish();
            });
    }
}
