<?php

namespace App\Filament\Manage\Resources\Users;

use App\Actions\SendTeamInvitationAction;
use App\Enums\InvitationStatus;
use App\Enums\PersonalDataSection as PersonalDataSectionEnum;
use App\Enums\Section as UserSection;
use App\Filament\Forms\Components\TimezoneSelect;
use App\Filament\Manage\NavigationGroup;
use App\Filament\Manage\Resources\Users\Pages\EditUser;
use App\Filament\Manage\Resources\Users\Pages\ListUsers;
use App\Filament\Manage\Resources\Users\Pages\ViewUser;
use App\Filament\Manage\Resources\Users\RelationManagers\MembershipsRelationManager;
use App\Filament\Manage\Schemas\PersonalDataSection;
use App\Models\User;
use App\Support\Palette;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Site;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationLabel = 'Users';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canInvite(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canInviteSiteAccess();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('invitation');
    }

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
                        TextEntry::make('invitation_status')
                            ->label('Invitation')
                            ->badge()
                            ->state(fn (User $record): InvitationStatus => $record->invitationStatus())
                            ->visible(fn (User $record): bool => $record->isPlaceholder()),
                        TextEntry::make('invited_email')
                            ->state(fn (User $record): ?string => $record->invitation?->email)
                            ->visible(fn (User $record): bool => $record->isPlaceholder() && $record->invitation !== null),
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
                TextColumn::make('invitation_status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (User $record): InvitationStatus => $record->invitationStatus()),
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
                Filter::make('not_accepted')
                    ->label('Not yet accepted')
                    ->query(fn (Builder $query): Builder => $query->whereNull('users.email')),
            ])
            ->recordActions([
                static::invitationActions(),
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
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function invitationActions(?User $record = null): ActionGroup
    {
        return ActionGroup::make([
            static::sendInvitationAction($record),
            static::revokeInvitationAction($record),
        ])
            ->record($record)
            ->label(__('Invitation'))
            ->button()
            ->color('gray')
            ->visible(fn (User $record): bool => static::canManageSiteInvitation($record));
    }

    public static function sendInvitationAction(?User $record = null): Action
    {
        return Action::make('sendInvitation')
            ->record($record)
            ->label(fn (User $record): string => match ($record->invitationStatus()) {
                InvitationStatus::Invited, InvitationStatus::Expired => __('Resend invitation'),
                InvitationStatus::Declined => __('Invite again'),
                default => __('Send invitation'),
            })
            ->visible(fn (User $record): bool => static::canManageSiteInvitation($record))
            ->modalDescription(fn (User $record): ?string => $record->invitationStatus() === InvitationStatus::Declined
                ? __('This person declined the invitation. Check the email address is correct before inviting them again.')
                : null)
            ->fillForm(fn (User $record): array => ['email' => $record->invitation?->email])
            ->schema([
                TextInput::make('email')
                    ->label('Email address to invite')
                    ->email()
                    ->required(),
            ])
            ->action(function (User $record, array $data, Schema $schema): void {
                $actor = auth()->user();

                if (! $actor instanceof User) {
                    return;
                }

                try {
                    app(SendTeamInvitationAction::class)->sendAdmin($actor, $record, $data['email']);
                } catch (ValidationException $exception) {
                    static::reportOnField($exception, $schema, 'email');
                }

                Notification::make()->success()->title(__('Invitation sent'))->send();
            });
    }

    public static function revokeInvitationAction(?User $record = null): Action
    {
        return Action::make('revokeInvitation')
            ->record($record)
            ->label(__('Revoke invitation'))
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (User $record): bool => static::canManageSiteInvitation($record) && $record->invitationStatus() === InvitationStatus::Invited)
            ->action(function (User $record): void {
                $actor = auth()->user();

                if (! $actor instanceof User) {
                    return;
                }

                app(SendTeamInvitationAction::class)->revokeAdmin($actor, $record);

                Notification::make()->success()->title(__('Invitation revoked'))->send();
            });
    }

    public static function reportOnField(ValidationException $exception, Schema $schema, string $field): never
    {
        $component = $schema->getFlatComponents(withHidden: true)[$field] ?? null;
        $path = $component instanceof Component ? $component->getStatePath() : $field;

        throw ValidationException::withMessages([
            (string) $path => static::validationMessage($exception),
        ]);
    }

    public static function validationMessage(ValidationException $exception): string
    {
        $message = collect($exception->errors())->flatten()->first();

        return is_string($message) ? $message : __('The invitation could not be sent.');
    }

    private static function canManageSiteInvitation(User $record): bool
    {
        return static::canInvite() && $record->isPlaceholder() && ! $record->teams()->exists();
    }
}
