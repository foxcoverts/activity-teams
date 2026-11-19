<?php

namespace App\Filament\Support\Infolists;

use App\Filament\Support\Actions\LocationMapAction;
use Filament\Infolists\Components\TextEntry;

final class LocationEntry
{
    public static function make(): TextEntry
    {
        return TextEntry::make('location')
            ->label(__('Location'))
            ->visible(fn ($record): bool => $record->location !== null)
            ->state(fn ($record): array => array_filter([
                $record->location->name,
                ...$record->location->addressArray(),
            ]))
            ->listWithLineBreaks()->limitList(1)->expandableLimitedList()
            ->afterLabel([
                LocationMapAction::make(),
            ]);
    }
}
