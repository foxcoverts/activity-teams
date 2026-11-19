<?php

namespace App\Filament\Support\Actions;

use App\Support\Palette;
use Darkclow4\FilamentMapPicker\Infolists\Components\MapEntry;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

final class LocationMapAction
{
    public static function make(): Action
    {
        return Action::make('map')
            ->label(__('View Map'))
            ->icon(Heroicon::Map)
            ->color(Palette::neutral())
            ->hidden(fn ($record): bool => ! $record->coords)
            ->modalHeading(fn ($record): string => $record->location->name)
            ->modalIcon(Heroicon::Map)
            ->modalCancelAction(false)
            ->extraModalWindowAttributes(['class' => 'venue-map-panel modal-mobile-full'])
            ->schema([
                MapEntry::make('map')
                    ->hidden(fn ($record): bool => ! $record->coords)
                    ->hiddenLabel()
                    ->state(fn ($record): array => [
                        'lat' => (float) $record->coords->latitude,
                        'lng' => (float) $record->coords->longitude,
                    ])
                    ->interactive()
                    ->height(360)
                    ->zoom(15),
            ])
            ->modalSubmitAction(false);
    }
}
