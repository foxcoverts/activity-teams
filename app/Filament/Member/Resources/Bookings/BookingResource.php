<?php

namespace App\Filament\Member\Resources\Bookings;

use App\Enums\BookingStatus;
use App\Filament\Bookings\LocationMapAction;
use App\Filament\Member\Resources\Bookings\Pages\ListBookings;
use App\Filament\Member\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Member\Resources\Bookings\RelationManagers\AttendeesRelationManager;
use App\Models\Booking;
use App\Models\User;
use App\Support\BookingWhen;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $recordTitleAttribute = 'group_name';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['activity', 'venue', 'attendees'])
            ->forUser(auth()->user())
            ->ordered();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(fn (?Booking $record): string => __(':Status booking', [
                    'status' => $record?->status->getLabel() ?? __('Booking'),
                ]))
                    ->icon(fn (?Booking $record): Heroicon => ($record?->status ?? BookingStatus::Tentative)->getIcon())
                    ->iconColor(fn (?Booking $record): array => ($record?->status ?? BookingStatus::Tentative)->getColor())
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('when')
                            ->label(__('When'))
                            ->state(fn (Booking $record): string => BookingWhen::format($record)),
                        TextEntry::make('location')
                            ->label(__('Location'))
                            ->state(fn (Booking $record): array => array_filter([
                                $record->location->name,
                                ...$record->location->addressArray(),
                            ]))
                            ->listWithLineBreaks()->limitList(1)->expandableLimitedList()
                            ->afterLabel([
                                LocationMapAction::make(),
                            ]),
                        TextEntry::make('activity.name')->label(__('Activity')),
                        TextEntry::make('group_name')
                            ->label(__('Group'))
                            ->columnSpanFull(),
                        TextEntry::make('notes')
                            ->label(__('Notes'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('lead_instructor_notes')
                            ->label(__('Lead instructor notes'))
                            ->placeholder('-')
                            ->visible(function (Booking $record): bool {
                                $user = auth()->user();
                                $record->loadMissing('team');

                                return $user instanceof User
                                    && $record->team !== null
                                    && ($record->isLead($user) || $user->manages($record->team));
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('group_name')
            ->columns([
                TextColumn::make('start_at')->dateTime('M j, Y H:i')->sortable(),
                TextColumn::make('activity.name')->searchable(),
                TextColumn::make('venue.name')->searchable(),
                TextColumn::make('group_name')->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->icon(fn (BookingStatus $state): Heroicon => $state->getIcon()),
            ])
            ->filters([
                SelectFilter::make('status')->options(BookingStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('start_at');
    }

    public static function getRelations(): array
    {
        return [
            AttendeesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookings::route('/'),
            'view' => ViewBooking::route('/{record}'),
        ];
    }
}
