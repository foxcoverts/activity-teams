<?php

namespace App\Filament\Teams\Resources\Documents\Tables;

use App\Filament\Documents\DownloadDocumentAction;
use App\Models\Document;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
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
                TextColumn::make('updated_at')
                    ->label(__('Updated'))
                    ->timezone(fn (): string => auth()->user()?->timezoneName() ?? (string) config('app.timezone'))
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                DownloadDocumentAction::make(),
            ])
            ->toolbarActions([]);
    }
}
