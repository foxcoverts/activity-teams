<?php

namespace App\Filament\Manage\Resources\NewsPosts\Actions;

use App\Models\NewsPost;
use App\Support\Palette;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class ScheduleAction
{
    public static function make(): Action
    {
        return Action::make('schedule')
            ->label(__('Schedule'))
            ->color(Palette::positive())
            ->icon(Heroicon::Clock)
            ->visible(fn (NewsPost $record): bool => is_null($record->deleted_at) && is_null($record->published_at))
            ->schema([
                DateTimePicker::make('published_at')
                    ->label(__('Publish at'))
                    ->seconds(false)
                    ->live()
                    ->required()
                    ->default(fn (): Carbon => now()->seconds(0)),
                Toggle::make('confirm_past')
                    ->label(__('This date is in the past'))
                    ->helperText(__('The post will appear in the feed without notifying members.'))
                    ->visible(fn (Get $get): bool => filled($get('published_at'))
                        && Carbon::parse($get('published_at'))->lt(now())),
            ])
            ->modalSubmitActionLabel(__('Schedule'))
            ->action(function (NewsPost $record, array $data, Action $action): void {
                $publishedAt = Carbon::parse($data['published_at'])->seconds(0);
                $confirmPast = (bool) ($data['confirm_past'] ?? false);

                if ($publishedAt->lt(now()) && ! $confirmPast) {
                    Notification::make()
                        ->danger()
                        ->title(__('Confirm that this date is in the past.'))
                        ->send();
                    $action->halt();
                }

                $record->scheduleAt($publishedAt, $confirmPast);
            });
    }
}
