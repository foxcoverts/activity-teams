<?php

namespace App\Filament\Manage\Resources\NewsPosts\Tables;

use App\Enums\NewsPostStatus;
use App\Filament\Manage\Resources\NewsPosts\Filters\NewsPostStatusFilter;
use App\Models\NewsPost;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class NewsPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('author.name'),
                TextColumn::make('status')
                    ->state(fn (NewsPost $record): NewsPostStatus => $record->getStatus())
                    ->badge(),
                TextColumn::make('published_at')
                    ->sortable()
                    ->label(__('Published'))
                    ->timezone(fn () => auth()->user()->timezone ?? config('app.timezone'))
                    ->since()
                    ->dateTimeTooltip('Y-m-d H:i'),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->hidden(fn ($livewire) => match ($livewire->getTableFilterState('trashed')) {
                        ['value' => '0'], ['value' => '1'] => false,
                        default => true,
                    }),
            ])
            ->filters([
                NewsPostStatusFilter::make(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
