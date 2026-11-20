<?php

namespace App\Filament\Admin\Resources\Keys\Schemas;

use App\Filament\Support\Infolists\PersonEntry;
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
                    ]),
                Section::make(__('Holder'))
                    ->icon(Heroicon::User)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        PersonEntry::make('holder.name')
                            ->hiddenLabel()
                            ->placeholder(__('In the cupboard'))
                            ->memberUrl(),
                    ]),
            ]);
    }
}
