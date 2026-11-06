<?php

namespace App\Filament\Teams\Resources\Documents\Tables;

use App\Filament\Documents\DownloadDocumentAction;
use App\Models\Document;
use App\Support\Palette;
use Awcodes\BadgeableColumn\Components\Badge;
use Awcodes\BadgeableColumn\Components\BadgeableColumn;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
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
                BadgeableColumn::make('title')
                    ->suffixBadges([
                        Badge::make('trashed')
                            ->label('Trashed')
                            ->color(Palette::destructive())
                            ->visible(fn (Document $record): bool => $record->trashed()),
                    ])
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
            ->filters([
                TrashedFilter::make()
                    ->visible(fn (): bool => auth()->user()->manages(Filament::getTenant())),
            ])
            ->recordActions([
                ViewAction::make(),
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
