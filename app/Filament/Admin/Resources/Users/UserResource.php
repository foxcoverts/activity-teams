<?php

namespace App\Filament\Admin\Resources\Users;

use App\Actions\DeleteUserAction;
use App\Actions\SendTeamInvitationAction;
use App\Enums\AccountRole;
use App\Enums\AccountStatus;
use App\Enums\InvitationStatus;
use App\Enums\MembershipRole;
use App\Enums\PersonalDataSection as PersonalDataSectionEnum;
use App\Enums\Section as UserSection;
use App\Filament\Admin\NavigationGroup;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Filament\Admin\Resources\Users\Pages\ViewUser;
use App\Filament\Admin\Resources\Users\RelationManagers\MembershipsRelationManager;
use App\Filament\Forms\Components\TimezoneSelect;
use App\Filament\RelationManagers\QualificationsRelationManager;
use App\Filament\Schemas\ContactFields;
use App\Filament\Schemas\PersonalDataSection;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
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
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Organisation;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

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
        return parent::getEloquentQuery()->with('invitation')->withTrashed();
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
                        Placeholder::make('password')
                            ->label(__('Password'))
                            ->content(__('Only the user can change their own password.'))
                            ->hiddenOn('view'),
                        PersonalDataSection::form(PersonalDataSectionEnum::ContactDetails, [
                            TextInput::make('email')
                                ->label('Email address')
                                ->email()
                                ->required(),
                            ContactFields::phoneInput('phone'),
                        ]),
                        PersonalDataSection::form(PersonalDataSectionEnum::EmergencyContact, [
                            TextInput::make('emergency_name'),
                            ContactFields::phoneInput('emergency_phone')
                                ->label(__('Emergency contact phone')),
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
                        TextEntry::make('invited_email')
                            ->state(fn (User $record): ?string => $record->invitation?->email)
                            ->visible(fn (User $record): bool => $record->isPlaceholder() && $record->invitation !== null),
                        TextEntry::make('status_badges')
                            ->hiddenLabel()
                            ->badge()
                            ->state(fn (User $record): array => static::statusBadges($record)),
                        PersonalDataSection::make(PersonalDataSectionEnum::ContactDetails, [
                            TextEntry::make('email')
                                ->label('Email address'),
                            ContactFields::phoneEntry('phone'),
                        ]),
                        PersonalDataSection::make(PersonalDataSectionEnum::EmergencyContact, [
                            TextEntry::make('emergency_name')
                                ->placeholder('-'),
                            ContactFields::phoneEntry('emergency_phone')
                                ->label(__('Emergency contact phone')),
                        ]),
                    ]),
            ]);
    }

    /**
     * @return list<AccountStatus|MembershipRole|AccountRole>
     */
    protected static function statusBadges(User $user): array
    {
        $badges = [$user->status()];

        if ($teamRole = $user->teamRole()) {
            $badges[] = $teamRole;
        }

        return [...$badges, ...$user->roles()];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(function (Builder $query, HasTable $livewire): Builder {
                $teamId = static::filteredTeamId($livewire);

                if ($teamId === null) {
                    return $query;
                }

                return $query->with([
                    'teams' => fn (BelongsToMany $teams): BelongsToMany => $teams->whereKey($teamId),
                ]);
            })
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (User $record): AccountStatus => $record->status()),
                TextColumn::make('roles')
                    ->badge()
                    ->state(fn (User $record): array => $record->roles()),
                TextColumn::make('section')
                    ->badge()
                    ->searchable(),
                TextColumn::make('teams.name')
                    ->label('Teams')
                    ->visible(fn (HasTable $livewire): bool => blank($livewire->getTableFilterState('team')['value'] ?? null))
                    ->badge(),
                TextColumn::make('team_role')
                    ->label('Team role')
                    ->badge()
                    ->hidden(fn (HasTable $livewire): bool => static::filteredTeamId($livewire) === null)
                    ->state(function (User $record, HasTable $livewire): ?MembershipRole {
                        $teamId = static::filteredTeamId($livewire);

                        return $teamId === null ? null : $record->roleOn($teamId);
                    }),
            ])
            ->filters([
                SelectFilter::make('team')
                    ->relationship('teams', 'name', hasEmptyOption: true),
                Filter::make('not_accepted')
                    ->label('Not yet accepted')
                    ->query(fn (Builder $query): Builder => $query->whereNull('users.email')->whereNull('users.deleted_at')),
            ])
            ->recordActions([
                static::invitationActions(),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->using(function (DeleteBulkAction $action, Collection $records): void {
                            $actor = auth()->user();

                            if (! $actor instanceof User) {
                                return;
                            }

                            $records->each(function (User $record) use ($actor, $action): void {
                                try {
                                    app(DeleteUserAction::class)->delete($actor, $record);
                                } catch (Throwable $exception) {
                                    $action->reportBulkProcessingFailure();
                                }
                            });
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            MembershipsRelationManager::class,
            QualificationsRelationManager::class,
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
        return static::canInvite() && $record->isPlaceholder() && ! $record->isSuspended() && ! $record->teams()->exists();
    }

    private static function filteredTeamId(HasTable $livewire): ?string
    {
        $value = $livewire->getTableFilterState('team')['value'] ?? null;

        if (! is_string($value) || $value === '' || $value === '__empty') {
            return null;
        }

        return $value;
    }
}
