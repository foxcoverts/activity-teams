<?php

namespace App\Filament\Teams\Resources\Keys\Schemas;

use App\Models\Key;
use App\Models\Team;
use Filament\Facades\Filament;
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
                        TextEntry::make('holder_name')
                            ->label(__('Holder'))
                            ->state(fn (Key $record): string => $record->holderName()),
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
                            ->state(fn (Key $record): array => $record->requirementSummariesFor(static::currentTeam()))
                            ->listWithLineBreaks()
                            ->placeholder('-'),
                    ]),
            ]);
    }

    public static function currentTeam(): ?Team
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Team ? $tenant : null;
    }
}
