<?php

namespace App\Filament\Teams\Resources\NewsPosts\Schemas;

use App\Models\NewsPost;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class NewsPostInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->heading(fn (NewsPost $record): HtmlString => new HtmlString(
                        view('filament.resources.news-posts.byline', ['post' => $record])->render(),
                    ))
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
