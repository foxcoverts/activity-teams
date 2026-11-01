<?php

namespace App\Filament\Manage\Resources\Documents\Tables;

use App\Filament\Documents\DownloadDocumentAction;
use App\Models\Document;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class DocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->columns([
                TextColumn::make('title')
                    ->description(fn (Document $record): ?HtmlString => filled($record->notes)
                        ? new HtmlString(Str::inlineMarkdown($record->notes))
                        : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('uploader.name')
                    ->label(__('Uploaded by')),
                TextColumn::make('updated_at')
                    ->label(__('Updated'))
                    ->timezone(fn (): string => auth()->user()?->timezoneName() ?? (string) config('app.timezone'))
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
                TextColumn::make('deleted_at')
                    ->dateTime('j M Y, H:i')
                    ->sortable()
                    ->hidden(fn ($livewire) => match ($livewire->getTableFilterState('trashed')) {
                        ['value' => '0'], ['value' => '1'] => false,
                        default => true,
                    }),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DownloadDocumentAction::make(),
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
