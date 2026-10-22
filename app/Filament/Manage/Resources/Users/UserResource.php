<?php

namespace App\Filament\Manage\Resources\Users;

use App\Enums\PersonalDataSection as PersonalDataSectionEnum;
use App\Enums\Section as UserSection;
use App\Filament\Forms\Components\TimezoneSelect;
use App\Filament\Manage\NavigationGroup;
use App\Filament\Manage\Resources\Users\Pages\CreateUser;
use App\Filament\Manage\Resources\Users\Pages\EditUser;
use App\Filament\Manage\Resources\Users\Pages\ListUsers;
use App\Filament\Manage\Resources\Users\Pages\ViewUser;
use App\Filament\Manage\Resources\Users\RelationManagers\MembershipsRelationManager;
use App\Filament\Manage\Schemas\PersonalDataSection;
use App\Models\User;
use App\Support\Palette;
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
use Filament\Schemas\Components\Section;
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
                Section::make(__('User'))
                    ->icon(Heroicon::User)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required(),
                        Select::make('section')
                            ->options(UserSection::class)
                            ->default('adult')
                            ->required(),
                        TimezoneSelect::make('timezone')
                            ->searchable(),
                        TextInput::make('password')
                            ->password()
                            ->dehydrated(fn ($state) => filled($state)),
                        PersonalDataSection::form(PersonalDataSectionEnum::ContactDetails, [
                            TextInput::make('email')
                                ->label('Email address')
                                ->email()
                                ->required(),
                            TextInput::make('phone')
                                ->tel(),
                        ]),
                        PersonalDataSection::form(PersonalDataSectionEnum::EmergencyContact, [
                            TextInput::make('emergency_name'),
                            TextInput::make('emergency_phone')
                                ->tel(),
                        ]),
                    ]),
                Section::make(__('Access'))
                    ->icon(Heroicon::ShieldCheck)
                    ->columns(1)
                    ->columnSpanFull()
                    ->visible(fn (): bool => (bool) auth()->user()?->is_system_admin)
                    ->schema([
                        Toggle::make('oversight'),
                        Toggle::make('is_system_admin')
                            ->label('System admin'),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('User'))
                    ->icon(Heroicon::User)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('section')
                            ->badge(),
                        TextEntry::make('timezone')
                            ->placeholder('-'),
                        TextEntry::make('status_badges')
                            ->hiddenLabel()
                            ->badge()
                            ->state(fn (User $record): array => static::statusBadges($record))
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'oversight' => __('Oversight'),
                                'system-admin' => __('System admin'),
                                'suspended' => __('Suspended'),
                                default => $state,
                            })
                            ->icon(fn (string $state): Heroicon => match ($state) {
                                'oversight' => Heroicon::Eye,
                                'system-admin' => Heroicon::ShieldCheck,
                                default => Heroicon::NoSymbol,
                            })
                            ->color(fn (string $state): array => $state === 'suspended' ? Palette::destructive() : Palette::neutral()),
                        PersonalDataSection::make(PersonalDataSectionEnum::ContactDetails, [
                            TextEntry::make('email')
                                ->label('Email address'),
                            TextEntry::make('phone')
                                ->placeholder('-'),
                        ]),
                        PersonalDataSection::make(PersonalDataSectionEnum::EmergencyContact, [
                            TextEntry::make('emergency_name')
                                ->placeholder('-'),
                            TextEntry::make('emergency_phone')
                                ->placeholder('-'),
                        ]),
                    ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    protected static function statusBadges(User $user): array
    {
        return array_values(array_filter([
            $user->oversight ? 'oversight' : null,
            $user->is_system_admin ? 'system-admin' : null,
            $user->suspended ? 'suspended' : null,
        ]));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('timezone')
                    ->searchable(),
                TextColumn::make('section')
                    ->badge()
                    ->searchable(),
                TextColumn::make('teams.name')
                    ->label('Teams')
                    ->badge(),
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
