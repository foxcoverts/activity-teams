<?php

namespace App\Filament\Teams\Resources\Keys\Schemas;

use App\Filament\Support\Infolists\PersonEntry;
use App\Filament\Teams\Resources\Keys\KeyResource;
use App\Models\Key;
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
                        PersonEntry::make('holder.name')
                            ->label(__('Holder'))
                            ->placeholder(__('In the cupboard'))
                            ->memberUrl(),
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
