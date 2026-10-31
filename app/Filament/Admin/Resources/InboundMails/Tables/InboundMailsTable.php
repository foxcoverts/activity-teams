<?php

namespace App\Filament\Admin\Resources\InboundMails\Tables;

use App\Enums\InboundMailOutcome;
use App\Models\InboundMail;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class InboundMailsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Received'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('from_address')
                    ->label(__('From'))
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('to_address')
                    ->label(__('To'))
                    ->searchable()
                    ->toggleable()
                    ->placeholder('-'),
                TextColumn::make('subject')
                    ->label(__('Subject'))
                    ->searchable()
                    ->limit(40)
                    ->placeholder('-'),
                TextColumn::make('calendar_method')
                    ->label(__('Method'))
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('outcome')
                    ->label(__('Outcome'))
                    ->badge()
                    ->placeholder('-'),
                TextColumn::make('reviewed_at')
                    ->label(__('Reviewed'))
                    ->dateTime()
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('needs_review')
                    ->label(__('Needs review'))
                    ->queries(
                        true: fn ($query) => $query->needsReview(),
                        false: fn ($query) => $query->whereNotNull('reviewed_at')
                            ->orWhereNotNull('handled_at'),
                        blank: fn ($query) => $query,
                    )
                    ->trueLabel(__('Needs review'))
                    ->falseLabel(__('Handled or reviewed'))
                    ->placeholder(__('All')),
                SelectFilter::make('outcome')
                    ->options(InboundMailOutcome::class),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('markReviewed')
                    ->label(__('Mark reviewed'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->visible(fn (InboundMail $record): bool => $record->reviewed_at === null)
                    ->action(function (InboundMail $record): void {
                        $record->markReviewed()->save();

                        Notification::make()
                            ->title(__('Marked reviewed'))
                            ->success()
                            ->send();
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
