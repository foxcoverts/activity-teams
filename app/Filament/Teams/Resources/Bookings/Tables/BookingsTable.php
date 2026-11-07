<?php

namespace App\Filament\Teams\Resources\Bookings\Tables;

use App\Enums\BookingAttendeeStatus;
use App\Enums\BookingStatus;
use App\Filament\Teams\Resources\Bookings\Actions\EditAction;
use App\Filament\Teams\Resources\Bookings\BookingStatusActions;
use App\Models\Booking;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('display_name')
            ->columns([
                TextColumn::make('start_at')->dateTime('M j, Y H:i')->sortable(),
                TextColumn::make('activity.name')
                    ->state(fn (Booking $record): string => $record->activityTitle())
                    ->icon(fn (Booking $record): ?Heroicon => filled($record->activity_label)
                        && $record->activity !== null
                        ? Heroicon::OutlinedInformationCircle
                        : null)
                    ->iconPosition('after')
                    ->tooltip(fn (Booking $record): ?string => filled($record->activity_label)
                        && $record->activity !== null
                        ? __('Original activity: :name', ['name' => $record->activity?->name])
                        : null)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where(function (Builder $query) use ($search): void {
                            $query
                                ->where('activity_label', 'like', "%{$search}%")
                                ->orWhereHas('activity', fn (Builder $activityQuery) => $activityQuery
                                    ->where('name', 'like', "%{$search}%"));
                        })),
                TextColumn::make('venue.name')->searchable(),
                TextColumn::make('status')
                    ->label(__('Booking Status'))
                    ->badge()
                    ->icon(fn (BookingStatus $state): Heroicon => $state->getIcon()),
                TextColumn::make('attendance')
                    ->state(fn (Booking $record): ?BookingAttendeeStatus => $record->attendees
                        ->firstWhere('id', auth()->id())
                        ?->attendance
                        ?->status)
                    ->badge()
                    ->label(__('Attendance')),
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
            ->defaultSort('start_at');
    }
}
