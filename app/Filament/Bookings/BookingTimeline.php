<?php

namespace App\Filament\Bookings;

use App\Support\ActivityChangeList;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Spatie\Activitylog\Models\Activity;

class BookingTimeline
{
    public static function entry(): RepeatableEntry
    {
        return RepeatableEntry::make('activities')
            ->label(__('Timeline'))
            ->schema([
                TextEntry::make('created_at')
                    ->label(__('When'))
                    ->dateTime(),
                TextEntry::make('causer.name')
                    ->label(__('Who'))
                    ->placeholder('—'),
                TextEntry::make('description')
                    ->label(__('Description')),
                TextEntry::make('attribute_changes')
                    ->label(__('Changes'))
                    ->formatStateUsing(function (mixed $state, Activity $record): string {
                        $changes = ActivityChangeList::summary($state);
                        $details = ActivityChangeList::summary($record->properties);

                        if ($changes !== '—' && $details !== '—') {
                            return $changes.' '.$details;
                        }

                        return $changes !== '—' ? $changes : $details;
                    }),
            ])
            ->columnSpanFull();
    }
}
