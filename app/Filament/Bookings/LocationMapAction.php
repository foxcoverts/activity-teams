<?php

namespace App\Filament\Bookings;

use App\Models\Booking;
use App\Support\Palette;
use Darkclow4\FilamentMapPicker\Infolists\Components\MapEntry;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class LocationMapAction
{
    public static function make(): Action
    {
        return Action::make('map')
            ->label(__('View Map'))
            ->icon(Heroicon::Map)
            ->color(Palette::neutral())
            ->hidden(fn (Booking $record): bool => ! $record->coords)
            ->modalHeading(fn (Booking $record): string => $record->location->name)
            ->modalIcon(Heroicon::Map)
            ->modalCancelAction(false)
            ->extraModalWindowAttributes(['class' => 'venue-map-panel'])
            ->schema([
                MapEntry::make('map')
                    ->hidden(fn (Booking $record): bool => ! $record->coords)
                    ->hiddenLabel()
                    ->state(fn (Booking $record): array => [
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
