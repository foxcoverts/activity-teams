<?php

namespace App\Filament\Bookings;

use App\Models\Booking;
use Carbon\CarbonInterface;
use Filament\Tables\Grouping\Group;
use Illuminate\Database\Eloquent\Builder;

final class BookingStartDateGroup
{
    public static function make(): Group
    {
        return Group::make('start_at')
            ->date()
            ->label(__('Date'))
            ->titlePrefixedWithLabel(false)
            ->getKeyFromRecordUsing(fn (Booking $record): string => $record->start_at
                ->timezone($record->timezone)
                ->toDateString())
            ->getTitleFromRecordUsing(fn (Booking $record): CarbonInterface => $record->start_at
                ->timezone($record->timezone))
            // Leave ordering to the Start column / defaultSort so asc↔desc sorting works.
            ->orderQueryUsing(fn (Builder $query): Builder => $query);
    }
}
