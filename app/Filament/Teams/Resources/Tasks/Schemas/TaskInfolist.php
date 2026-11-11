<?php

namespace App\Filament\Teams\Resources\Tasks\Schemas;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Support\Palette;
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
                Section::make(fn (?Task $record): string => __(':Status task', [
                    'status' => ($record?->status ?? TaskStatus::NeedsAction)->getHeadingLabel(),
                ]))
                    ->icon(fn (?Task $record): Heroicon => ($record?->status ?? TaskStatus::NeedsAction)->getIcon())
                    ->iconColor(fn (?Task $record): array => $record?->isOverdue()
                        ? Palette::destructive()
                        : ($record?->status ?? TaskStatus::NeedsAction)->getColor())
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('description')
                            ->label(__('Notes'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('location')
                            ->label(__('Venue'))
                            ->state(fn (Task $record): ?string => filled($record->location?->name)
                                ? implode("\n", array_filter([
                                    $record->location->name,
                                    ...$record->location->addressArray(),
                                ]))
                                : null)
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('priority')
                            ->badge(),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('started_at')
                            ->label(__('Started'))
                            ->dateTime('j M Y, H:i')
                            ->timezone(fn (): string => auth()->user()?->timezoneName() ?? (string) config('app.timezone'))
                            ->placeholder('-')
                            ->visible(fn (Task $record): bool => $record->status === TaskStatus::InProcess
                                || $record->started_at !== null),
                        TextEntry::make('due_at')
                            ->label(__('Due'))
                            ->dateTime('j M Y, H:i')
                            ->timezone(fn (): string => auth()->user()?->timezoneName() ?? (string) config('app.timezone'))
                            ->placeholder('-')
                            ->color(fn (Task $record): ?array => $record->isOverdue()
                                ? Palette::destructive()
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
