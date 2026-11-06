<?php

namespace App\Filament\Teams\Resources\Documents\Schemas;

use App\Models\Document;
use App\Support\Palette;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class DocumentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Document'))
                ->icon(fn (Document $record): Heroicon => $record->trashed() ? Heroicon::OutlinedTrash : Heroicon::OutlinedDocument)
                ->iconColor(fn (Document $record): ?array => $record->trashed() ? Palette::destructive() : null)
                ->columns(1)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('title'),
                    TextEntry::make('notes')
                        ->state(fn (Document $record): ?HtmlString => filled($record->notes)
                            ? new HtmlString(Str::inlineMarkdown($record->notes))
                            : null)
                        ->placeholder('-'),
                    TextEntry::make('updated_at')
                        ->label(__('Updated'))
                        ->timezone(fn (): string => auth()->user()?->timezoneName() ?? (string) config('app.timezone'))
                        ->dateTime('j M Y, H:i'),
                    TextEntry::make('deleted_at')
                        ->label(__('Deleted'))
                        ->timezone(fn (): string => auth()->user()?->timezoneName() ?? (string) config('app.timezone'))
                        ->dateTime('j M Y, H:i')
                        ->visible(fn (Document $record): bool => $record->trashed()),
                ]),
        ]);
    }
}
