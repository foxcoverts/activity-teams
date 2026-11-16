<?php

namespace App\Filament\Teams\Resources\Bookings\Tables;

use App\Enums\BookingStatus;
use App\Filament\Bookings\BookingActivityColumn;
use App\Filament\Bookings\BookingStartDateGroup;
use App\Filament\Teams\Resources\Bookings\Actions\EditAction;
use App\Filament\Teams\Resources\Bookings\BookingStatusActions;
use App\Filament\Teams\Resources\Bookings\Pages\ListBookings;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                BookingActivityColumn::attendanceStatusColumn(),
            ])
            ->filters([
                SelectFilter::make('status')->options(BookingStatus::class),
                Filter::make('schedule')
                    ->label(__('Dates'))
                    ->schema([
                        DatePicker::make('from')
                            ->label(__('From')),
                        DatePicker::make('to')
                            ->label(__('To')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $tz = (string) config('app.timezone');
                        $from = filled($data['from'] ?? null)
                            ? CarbonImmutable::parse($data['from'], $tz)->startOfDay()
                            : null;
                        $to = filled($data['to'] ?? null)
                            ? CarbonImmutable::parse($data['to'], $tz)->endOfDay()
                            : null;

                        if ($from instanceof CarbonImmutable && $to instanceof CarbonImmutable) {
                            return $query->overlapping($from, $to);
                        }

                        if ($from instanceof CarbonImmutable) {
                            return $query->where($query->getModel()->qualifyColumn('end_at'), '>=', $from);
                        }

                        if ($to instanceof CarbonImmutable) {
                            return $query->where($query->getModel()->qualifyColumn('start_at'), '<=', $to);
                        }

                        return $query;
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (filled($data['from'] ?? null)) {
                            $indicators[] = __('From :date', [
                                'date' => CarbonImmutable::parse($data['from'])->toFormattedDateString(),
                            ]);
                        }

                        if (filled($data['to'] ?? null)) {
                            $indicators[] = __('To :date', [
                                'date' => CarbonImmutable::parse($data['to'])->toFormattedDateString(),
                            ]);
                        }

                        return $indicators;
                    }),
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
