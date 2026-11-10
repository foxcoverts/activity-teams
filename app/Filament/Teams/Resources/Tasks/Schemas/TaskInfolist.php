<?php

namespace App\Filament\Teams\Resources\Tasks\Schemas;

use App\Models\Task;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class TaskInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Task'))
                    ->icon(fn (Task $record): Heroicon => $record->status->getIcon())
                    ->iconColor(fn (Task $record): array => $record->status->getColor())
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('summary')
                            ->label(__('Title')),
                        TextEntry::make('description')
                            ->label(__('Notes'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('location')
                            ->placeholder('-'),
                        TextEntry::make('priority')
                            ->badge(),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('started_at')
                            ->label(__('Started'))
                            ->dateTime('j M Y, H:i')
                            ->timezone(fn (): string => auth()->user()?->timezoneName() ?? (string) config('app.timezone'))
                            ->placeholder('-'),
                        TextEntry::make('due_at')
                            ->label(__('Due'))
                            ->dateTime('j M Y, H:i')
                            ->timezone(fn (): string => auth()->user()?->timezoneName() ?? (string) config('app.timezone'))
                            ->placeholder('-')
                            ->color(fn (Task $record): ?array => $record->isOverdue()
                                ? $record->status->getColor()
                                : null),
                        TextEntry::make('completed_at')
                            ->label(__('Completed'))
                            ->dateTime('j M Y, H:i')
                            ->timezone(fn (): string => auth()->user()?->timezoneName() ?? (string) config('app.timezone'))
                            ->placeholder('-')
                            ->visible(fn (Task $record): bool => $record->completed_at !== null),
                    ]),
            ]);
    }
}
