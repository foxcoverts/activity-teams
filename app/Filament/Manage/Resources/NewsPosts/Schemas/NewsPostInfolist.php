<?php

namespace App\Filament\Manage\Resources\NewsPosts\Schemas;

use App\Enums\NewsPostStatus;
use App\Models\NewsPost;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class NewsPostInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(fn (NewsPost $record): string => $record->title)
                    ->icon(fn (NewsPost $record): Heroicon => $record->getStatus()->getIcon())
                    ->iconColor(fn (NewsPost $record): array => $record->getStatus()->getColor())
                    ->description(fn (NewsPost $record): string => __('By :author', ['author' => $record->author->name]))
                    ->afterHeader([
                        Text::make(fn (NewsPost $record): string => $record->getStatus()->getLabel())
                            ->badge()
                            ->color(fn (NewsPost $record): array => $record->getStatus()->getColor())
                            ->icon(fn (NewsPost $record): Heroicon => $record->getStatus()->getIcon())
                            ->tooltip(fn (NewsPost $record): ?string => match ($record->getStatus()) {
                                NewsPostStatus::Scheduled, NewsPostStatus::Published => $record->published_at?->format('j M Y, H:i'),
                                default => null,
                            }),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('body')
                            ->hiddenLabel()
                            ->markdown()
                            ->columnSpanFull(),
                    ]),
                Section::make(__('Post details'))
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->columns(1)
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('author.name')
                            ->label(__('Author'))
                            ->placeholder('-'),
                        TextEntry::make('published_at')
                            ->dateTime('j M Y, H:i')
                            ->placeholder('-'),
                        TextEntry::make('deleted_at')
                            ->dateTime('j M Y, H:i')
                            ->visible(fn (NewsPost $record): bool => $record->trashed()),
                    ]),
            ]);
    }
}
