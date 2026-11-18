<?php

namespace App\Filament\Teams\Resources\Keys\Schemas;

use App\Filament\Teams\Resources\Keys\KeyResource;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Models\Key;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class KeyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Key'))
                    ->icon(Heroicon::OutlinedKey)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('name'),
                        ImageEntry::make('holder_avatar')
                            ->hiddenLabel()
                            ->state(fn (Key $record): ?string => $record->holder?->getFilamentAvatarUrl())
                            ->alt(fn (Key $record): string => $record->holder?->name ?? '')
                            ->imageSize(40)
                            ->circular()
                            ->visible(fn (Key $record): bool => $record->holder !== null),
                        TextEntry::make('holder_name')
                            ->label(__('Holder'))
                            ->state(fn (Key $record): string => $record->holderName())
                            ->url(fn (Key $record): ?string => MemberResource::viewUrlFor(
                                $record->holder,
                                KeyResource::currentTeam(),
                            ))
                            ->color(fn (Key $record): string => $record->holder !== null ? 'primary' : 'gray'),
                    ]),
                Section::make(__('Unlocks'))
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('key_descriptions')
                            ->hiddenLabel()
                            ->state(fn (Key $record): array => $record->lockDescriptions() ?: [__('This key opens no locks.')])
                            ->listWithLineBreaks()
                            ->placeholder('-'),
                        TextEntry::make('required_for')
                            ->label(__('Required for'))
                            ->state(fn (Key $record): array => $record->requirementSummariesFor(KeyResource::currentTeam()))
                            ->listWithLineBreaks()
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
