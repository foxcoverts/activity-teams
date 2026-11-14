<?php

namespace App\Filament\Teams\Resources\BookingDebriefs\Tables;

use App\Models\Booking;
use App\Models\BookingDebrief;
use App\Support\BookingWhen;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingDebriefsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc(
                Booking::query()
                    ->select('start_at')
                    ->whereColumn('bookings.id', 'booking_debriefs.booking_id')
                    ->limit(1),
            ))
            ->columns([
                TextColumn::make('booking.activity_title')
                    ->label(__('Activity'))
                    ->state(fn (BookingDebrief $record): string => $record->booking?->activityTitle() ?? '-')
                    ->description(fn (BookingDebrief $record): ?string => $record->booking?->audience?->name)
                    ->searchable(query: function ($query, string $search): void {
                        $query->whereHas('booking', function ($bookings) use ($search): void {
                            $bookings
                                ->where('activity_label', 'like', "%{$search}%")
                                ->orWhere('group_name', 'like', "%{$search}%")
                                ->orWhereHas(
                                    'activity',
                                    fn ($activities) => $activities->where('name', 'like', "%{$search}%"),
                                );
                        });
                    }),
                TextColumn::make('booking.when')
                    ->label(__('When'))
                    ->state(fn (BookingDebrief $record): string => $record->booking instanceof Booking
                        ? BookingWhen::format($record->booking)
                        : '-')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy(
                            Booking::query()
                                ->select('start_at')
                                ->whereColumn('bookings.id', 'booking_debriefs.booking_id')
                                ->limit(1),
                            $direction,
                        );
                    }),
                TextColumn::make('hours')
                    ->label(__('Hours'))
                    ->placeholder('-')
                    ->alignEnd(),
                TextColumn::make('participant_count')
                    ->label(__('Participants'))
                    ->placeholder('-')
                    ->alignEnd(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
