<?php

namespace App\Filament\Teams\Resources\Bookings\Tables;

use App\Enums\BookingStatus;
use App\Filament\Bookings\BookingActivityColumn;
use App\Filament\Bookings\BookingStartDateGroup;
use App\Filament\Teams\Resources\Bookings\Actions\EditAction;
use App\Filament\Teams\Resources\Bookings\BookingStatusActions;
use App\Filament\Teams\Resources\Bookings\Pages\ListBookings;
use App\Models\Booking;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('display_name')
            ->defaultGroup(BookingStartDateGroup::make())
            ->groupingSettingsHidden()
            ->recordClasses(fn (Booking $record): array => BookingActivityColumn::attendanceRecordClasses($record))
            ->columns([
                TextColumn::make('start_at')
                    ->label(__('Start'))
                    ->formatStateUsing(fn (mixed $state, Booking $record): string => $record->start_at
                        ->timezone($record->timezone)
                        ->format('H:i'))
                    ->verticallyAlignStart()
                    ->width('1%')
                    ->grow(false)
                    ->sortable(),
                BookingActivityColumn::make(),
            ])
            ->filters([
                SelectFilter::make('status')->options(BookingStatus::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    ...BookingStatusActions::actions(),
                    DeleteAction::make(),
                ]),
            ])
            ->defaultSort(
                'start_at',
                fn (HasTable $livewire): string => $livewire instanceof ListBookings
                    && $livewire->activeTab === 'history'
                        ? 'desc'
                        : 'asc',
            );
    }
}
