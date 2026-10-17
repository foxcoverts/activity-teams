<?php

namespace App\Filament\Manage\Resources\Users;

use App\Enums\Role;
use App\Enums\Section;
use App\Filament\Manage\Resources\Users\Pages\CreateUser;
use App\Filament\Manage\Resources\Users\Pages\EditUser;
use App\Filament\Manage\Resources\Users\Pages\ListUsers;
use App\Filament\Manage\Resources\Users\Pages\ViewUser;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use App\Enums\Accreditation;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->dehydrated(fn ($state) => filled($state)),
                TextInput::make('timezone'),
                Select::make('role')
                    ->options(Role::class)
                    ->default('guest')
                    ->required(),
                Select::make('section')
                    ->options(Section::class)
                    ->default('adult')
                    ->required(),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('emergency_name'),
                TextInput::make('emergency_phone')
                    ->tel(),
                CheckboxList::make('accreditations')
                    ->options(Accreditation::class)
                    ->columns(2),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label('ID'),
                TextEntry::make('name'),
                TextEntry::make('email')
                    ->label('Email address'),
                TextEntry::make('email_verified_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('timezone')
                    ->placeholder('-'),
                TextEntry::make('role')
                    ->badge(),
                TextEntry::make('section')
                    ->badge(),
                TextEntry::make('phone')
                    ->placeholder('-'),
                TextEntry::make('emergency_name')
                    ->placeholder('-'),
                TextEntry::make('emergency_phone')
                    ->placeholder('-'),
                TextEntry::make('accreditations'),
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
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('timezone')
                    ->searchable(),
                TextColumn::make('role')
                    ->badge()
                    ->searchable(),
                TextColumn::make('section')
                    ->badge()
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('emergency_name')
                    ->searchable(),
                TextColumn::make('emergency_phone')
                    ->searchable(),
                TextColumn::make('accreditations')
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
