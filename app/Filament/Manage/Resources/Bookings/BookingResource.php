<?php

namespace App\Filament\Manage\Resources\Bookings;

use App\Enums\BookingStatus;
use App\Filament\Manage\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Manage\Resources\Bookings\Pages\EditBooking;
use App\Filament\Manage\Resources\Bookings\Pages\ListBookings;
use App\Filament\Manage\Resources\Bookings\Pages\ViewBooking;
use App\Filament\Manage\Resources\Bookings\RelationManagers\AttendeesRelationManager;
use App\Models\Booking;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'group_name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('activity_id')
                    ->relationship('activity', 'name')
                    ->required(),
                Select::make('venue_id')
                    ->relationship('venue', 'name')
                    ->required(),
                DateTimePicker::make('start_at')->required(),
                DateTimePicker::make('end_at')->required(),
                TextInput::make('timezone')
                    ->default('Europe/London')
                    ->required(),
                Select::make('status')
                    ->options(BookingStatus::class)
                    ->default(BookingStatus::Tentative)
                    ->required(),
                TextInput::make('cancel_reason')
                    ->visible(fn ($get) => $get('status') === BookingStatus::Cancelled->value)
                    ->dehydrated(false),
                TextInput::make('group_name')
                    ->required(),
                Textarea::make('notes')
                    ->columnSpanFull(),
                Select::make('lead_instructor_id')
                    ->relationship('leadInstructor', 'name'),
                Textarea::make('lead_instructor_notes')
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID'),
                TextEntry::make('activity.name')
                    ->label('Activity'),
                TextEntry::make('venue.name')
                    ->label('Venue'),
                TextEntry::make('start_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('end_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('timezone')
                    ->placeholder('-'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('group_name'),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('leadInstructor.name')
                    ->label('Lead instructor')
                    ->placeholder('-'),
                TextEntry::make('lead_instructor_notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('group_name')
            ->columns([
                TextColumn::make('activity.name')
                    ->searchable(),
                TextColumn::make('venue.name')
                    ->searchable(),
                TextColumn::make('start_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('end_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('group_name')
                    ->searchable(),
                TextColumn::make('leadInstructor.name')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
            'create' => CreateBooking::route('/create'),
            'view' => ViewBooking::route('/{record}'),
            'edit' => EditBooking::route('/{record}/edit'),
        ];
    }
}
