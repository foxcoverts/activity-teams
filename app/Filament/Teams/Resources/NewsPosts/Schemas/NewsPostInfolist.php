<?php

namespace App\Filament\Teams\Resources\NewsPosts\Schemas;

use App\Models\NewsPost;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class NewsPostInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->description(fn (NewsPost $record): string => implode(' · ', array_filter([
                        $record->author?->name ? __('By :author', ['author' => $record->author->name]) : null,
                        $record->published_at?->format('j M Y, H:i'),
                    ])))
                    ->afterHeader([
                        static::statusBadge(),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('body')
                            ->hiddenLabel()
                            ->markdown()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected static function statusBadge(): Text
    {
        return Text::make(fn (NewsPost $record): string => $record->getStatus()->getLabel())
            ->color(fn (NewsPost $record) => $record->getStatus()->getColor())
            ->icon(fn (NewsPost $record) => $record->getStatus()->getIcon())
            ->visible(fn (NewsPost $record) => (bool) $record->getStatus() && auth()->user()->can('update', $record))
            ->badge();
    }
}
