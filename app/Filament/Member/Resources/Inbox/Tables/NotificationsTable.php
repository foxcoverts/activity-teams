<?php

namespace App\Filament\Member\Resources\Inbox\Tables;

use App\Filament\Member\Resources\Inbox\Actions\DeleteSelectedNotificationsBulkAction;
use App\Filament\Member\Resources\Inbox\Actions\MarkAllReadAction;
use App\Filament\Member\Resources\Inbox\Actions\MarkSelectedReadBulkAction;
use App\Models\InboxNotification;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NotificationsTable
{
    public static function configure(Table $table): Table
    {
        $weight = fn (InboxNotification $record): FontWeight => $record->read_at === null
            ? FontWeight::Bold
            : FontWeight::Normal;

        return $table
            ->extraAttributes(['class' => 'inbox-notifications'])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading(__('No notifications'))
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw('read_at is null desc')
                ->orderByDesc('created_at'))
            ->recordClasses(fn (InboxNotification $record): array => $record->read_at === null ? [] : [
                'bg-gray-100',
                'dark:bg-white/10',
            ])
            ->columns([
                TextColumn::make('body')
                    ->label(__('Notification'))
                    ->getStateUsing(fn (InboxNotification $record): string => (string) ($record->data['body'] ?? ''))
                    ->weight($weight)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label(__('When'))
                    ->timezone(fn () => auth()->user()->timezone ?? config('app.timezone'))
                    ->since()
                    ->dateTimeTooltip('j M Y, H:i')
                    ->weight($weight)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('read_state')
                    ->label(__('Status'))
                    ->placeholder(__('All'))
                    ->options([
                        'unread' => __('Unread'),
                        'read' => __('Read'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'unread' => $query->whereNull('read_at'),
                            'read' => $query->whereNotNull('read_at'),
                            default => $query,
                        };
                    }),
            ])
            ->toolbarActions([
                MarkAllReadAction::make(),
                MarkSelectedReadBulkAction::make(),
                DeleteSelectedNotificationsBulkAction::make(),
            ]);
    }
}
