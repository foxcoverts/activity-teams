<?php

namespace App\Filament\Manage\Resources\Members;

use App\Actions\SendTeamInvitationAction;
use App\Enums\AccountStatus;
use App\Enums\InvitationStatus;
use App\Enums\MembershipRole;
use App\Enums\PersonalDataSection as PersonalDataSectionEnum;
use App\Enums\Section as MemberSection;
use App\Filament\Manage\NavigationGroup;
use App\Filament\Manage\Resources\Members\Pages\EditMember;
use App\Filament\Manage\Resources\Members\Pages\ListMembers;
use App\Filament\Manage\Resources\Members\Pages\ViewMember;
use App\Filament\RelationManagers\QualificationsRelationManager;
use App\Filament\Schemas\ContactFields;
use App\Filament\Schemas\PersonalDataSection;
use App\Models\Team;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Component as LivewireComponent;

class MemberResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Team;

    protected static ?string $navigationLabel = 'Members';

    protected static ?string $modelLabel = 'member';

    protected static ?string $slug = 'members';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isScopedToTenant = false;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        $team = static::currentTeam();

        return $user instanceof User
            && $team instanceof Team
            && ($user->manages($team) || $user->isSystemAdmin());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->withTrashed();
        $team = static::currentTeam();

        if (! $team instanceof Team) {
            return $query->whereRaw('0 = 1');
        }

        return $query->with('invitation')->whereHas(
            'teams',
            fn (Builder $teams) => $teams->whereKey($team->getKey()),
        );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Member'))
                    ->icon(Heroicon::User)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required(),
                        Select::make('section')
                            ->options(MemberSection::class)
                            ->required(),
                        Placeholder::make('role')
                            ->label(__('Role'))
                            ->badge()
                            ->state(fn (User $record): ?MembershipRole => static::roleOnCurrentTeam($record)),
                        PersonalDataSection::form(PersonalDataSectionEnum::ContactDetails, [
                            TextInput::make('email')
                                ->email()
                                ->required(fn (?User $record): bool => ! $record?->isPlaceholder())
                                ->disabled(fn (?User $record): bool => $record?->isPlaceholder() ?? false),
                            ContactFields::phoneInput('phone'),
                        ]),
                        PersonalDataSection::form(PersonalDataSectionEnum::EmergencyContact, [
                            TextInput::make('emergency_name'),
                            ContactFields::phoneInput('emergency_phone')
                                ->label(__('Emergency contact phone')),
                        ]),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Member'))
                    ->icon(Heroicon::User)
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('section')
                            ->badge(),
                        TextEntry::make('membership_role')
                            ->label('Role')
                            ->badge()
                            ->state(fn (User $record): ?MembershipRole => static::roleOnCurrentTeam($record)),
                        TextEntry::make('invited_email')
                            ->state(fn (User $record): ?string => $record->invitation?->email)
                            ->visible(fn (User $record): bool => $record->isPlaceholder() && $record->invitation !== null),
                        TextEntry::make('status')
                            ->hiddenLabel()
                            ->badge()
                            ->state(fn (User $record): AccountStatus|string => $record->status()),
                        PersonalDataSection::make(PersonalDataSectionEnum::ContactDetails, [
                            TextEntry::make('email'),
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('membership_role')
                    ->label('Role')
                    ->badge()
                    ->state(fn (User $record): ?MembershipRole => static::roleOnCurrentTeam($record)),
                TextColumn::make('status')
                    ->state(fn (User $record): AccountStatus|string => $record->status())
                    ->badge(),
            ])
            ->filters([
                Filter::make('not_accepted')
                    ->label('Not yet accepted')
                    ->query(fn (Builder $query): Builder => $query->whereNull('users.email')->whereNull('users.deleted_at')),
            ])
            ->recordActions([
                static::invitationActions(),
                ViewAction::make(),
                EditAction::make(),
                static::changeRoleAction(),
                static::removeFromTeamAction(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            QualificationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembers::route('/'),
            'view' => ViewMember::route('/{record}'),
            'edit' => EditMember::route('/{record}/edit'),
        ];
    }

    public static function currentTeam(): ?Team
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Team ? $tenant : null;
    }

    public static function canInvite(): bool
    {
        $user = auth()->user();
        $team = static::currentTeam();

        return $user instanceof User && $team instanceof Team && $user->canInviteTo($team);
    }

    public static function canManageInvitation(User $record): bool
    {
        return static::canInvite() && $record->isPlaceholder() && ! $record->isSuspended();
    }

    public static function canManage(): bool
    {
        $user = auth()->user();
        $team = static::currentTeam();

        return $user instanceof User && $team instanceof Team && $user->manages($team);
    }

    public static function canChangeRole(): bool
    {
        $user = auth()->user();
        $team = static::currentTeam();

        return $user instanceof User
            && $team instanceof Team
            && ($user->isSystemAdmin() || $user->manages($team));
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
            ->visible(fn (User $record): bool => static::canManageInvitation($record));
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
            ->visible(fn (User $record): bool => static::canManageInvitation($record))
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
                try {
                    app(SendTeamInvitationAction::class)->send(auth()->user(), static::currentTeam(), $record, $data['email']);
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
            ->visible(fn (User $record): bool => static::canManageInvitation($record) && $record->invitationStatus() === InvitationStatus::Invited)
            ->action(function (User $record): void {
                app(SendTeamInvitationAction::class)->revoke(auth()->user(), static::currentTeam(), $record);

                Notification::make()->success()->title(__('Invitation revoked'))->send();
            });
    }

    public static function changeRoleAction(?User $record = null): Action
    {
        return Action::make('changeRole')
            ->record($record)
            ->label(__('Change role'))
            ->visible(fn (?User $record): bool => static::canChangeRole() && $record instanceof User && ! $record->trashed())
            ->disabled(fn (User $record): bool => static::cannotStepDown($record))
            ->tooltip(fn (User $record): ?string => static::stepDownHint($record))
            ->fillForm(fn (User $record): array => [
                'role' => static::roleOnCurrentTeam($record)?->value,
            ])
            ->schema([
                ToggleButtons::make('role')
                    ->options(MembershipRole::class)
                    ->inline()
                    ->required(),
            ])
            ->action(function (User $record, array $data, Schema $schema): void {
                static::authorizeRoleChange();

                $role = $data['role'] instanceof MembershipRole
                    ? $data['role']
                    : MembershipRole::from((string) $data['role']);

                try {
                    static::currentTeam()?->grant($record, $role);
                } catch (ValidationException $exception) {
                    static::reportOnRole($exception, $schema);
                }
            })
            ->after(function (LivewireComponent $livewire, User $record): void {
                if ($livewire instanceof ViewMember) {
                    $livewire->redirect(static::getUrl('view', ['record' => $record]));
                }
            });
    }

    public static function removeFromTeamAction(?User $record = null): Action
    {
        return Action::make('leave')
            ->record($record)
            ->label(__('Remove from team'))
            ->color('danger')
            ->visible(fn (?User $record): bool => static::canManage() && $record instanceof User && ! $record->trashed())
            ->disabled(fn (User $record): bool => static::cannotStepDown($record))
            ->tooltip(fn (User $record): ?string => static::stepDownHint($record))
            ->requiresConfirmation()
            ->action(function (User $record): void {
                static::authorizeManagement();

                try {
                    static::currentTeam()?->recordLeft($record);
                } catch (ValidationException $exception) {
                    static::reportAsNotification($exception);
                }
            })
            ->successRedirectUrl(static::getUrl('index'));
    }

    public static function cannotStepDown(User $user): bool
    {
        $team = static::currentTeam();

        return $team instanceof Team && $team->isOnlyManager($user);
    }

    public static function stepDownHint(User $user): ?string
    {
        return static::cannotStepDown($user) ? Team::lastManagerMessage() : null;
    }

    public static function roleOnCurrentTeam(User $user): ?MembershipRole
    {
        $team = static::currentTeam();

        if (! $team instanceof Team) {
            return null;
        }

        $role = $user->memberships()
            ->where('team_id', $team->getKey())
            ->whereNull('left_at')
            ->value('role');

        return $role instanceof MembershipRole ? $role : MembershipRole::tryFrom((string) $role);
    }

    public static function reportOnRole(ValidationException $exception, Schema $schema): never
    {
        static::reportOnField($exception, $schema, 'role');
    }

    public static function reportOnField(ValidationException $exception, Schema $schema, string $field): never
    {
        $component = $schema->getFlatComponents(withHidden: true)[$field] ?? null;
        $path = $component instanceof Component ? $component->getStatePath() : $field;

        throw ValidationException::withMessages([
            (string) $path => static::validationMessage($exception),
        ]);
    }

    public static function reportAsNotification(ValidationException $exception): void
    {
        Notification::make()
            ->danger()
            ->title(static::validationMessage($exception))
            ->send();
    }

    private static function authorizeManagement(): void
    {
        if (! static::canManage()) {
            throw new AuthorizationException;
        }
    }

    private static function authorizeRoleChange(): void
    {
        if (! static::canChangeRole()) {
            throw new AuthorizationException;
        }
    }

    public static function validationMessage(ValidationException $exception): string
    {
        $message = collect($exception->errors())->flatten()->first();

        return is_string($message) ? $message : __('Appoint another manager before this one steps down.');
    }
}
