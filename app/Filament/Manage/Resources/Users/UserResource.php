<?php

namespace App\Filament\Manage\Resources\Users;

use App\Enums\Section;
use App\Filament\Manage\NavigationGroup;
use App\Filament\Manage\Resources\Users\Pages\CreateUser;
use App\Filament\Manage\Resources\Users\Pages\EditUser;
use App\Filament\Manage\Resources\Users\Pages\ListUsers;
use App\Filament\Manage\Resources\Users\Pages\ViewUser;
use App\Filament\Manage\Resources\Users\RelationManagers\MembershipsRelationManager;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Site;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationLabel = 'Users';

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
                Select::make('section')
                    ->options(Section::class)
                    ->default('adult')
                    ->required(),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('emergency_name'),
                TextInput::make('emergency_phone')
                    ->tel(),
                Toggle::make('oversight'),
                Toggle::make('is_system_admin')
                    ->label('System admin'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('email')
                    ->label('Email address'),
                TextEntry::make('email_verified_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('timezone')
                    ->placeholder('-'),
                TextEntry::make('section')
                    ->badge(),
                TextEntry::make('phone')
                    ->placeholder('-'),
                TextEntry::make('emergency_name')
                    ->placeholder('-'),
                TextEntry::make('emergency_phone')
                    ->placeholder('-'),
                TextEntry::make('oversight')
                    ->badge(),
                TextEntry::make('is_system_admin')
                    ->label('System admin')
                    ->badge(),
                TextEntry::make('suspended')
                    ->badge(),
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
                TextColumn::make('section')
                    ->badge()
                    ->searchable(),
                TextColumn::make('teams.name')
                    ->label('Teams')
                    ->badge(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('emergency_name')
                    ->searchable(),
                TextColumn::make('emergency_phone')
                    ->searchable(),
                TextColumn::make('oversight')
                    ->badge(),
                TextColumn::make('suspended')
                    ->badge(),
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
            MembershipsRelationManager::class,
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
