<?php

namespace App\Filament\Teams\Resources\Members;

use App\Actions\SendTeamInvitationAction;
use App\Enums\InvitationStatus;
use App\Enums\MembershipRole;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Filament\RelationManagers\QualificationsRelationManager;
use App\Filament\Support\RefreshesNavigationChrome;
use App\Filament\Teams\NavigationGroup;
use App\Filament\Teams\Resources\Members\Pages\EditMember;
use App\Filament\Teams\Resources\Members\Pages\ListMembers;
use App\Filament\Teams\Resources\Members\Pages\ViewMember;
use App\Filament\Teams\Resources\Members\RelationManagers\AccessRelationManager;
use App\Filament\Teams\Resources\Members\RelationManagers\KeysRelationManager;
use App\Filament\Teams\Resources\Members\Schemas\MemberForm;
use App\Filament\Teams\Resources\Members\Schemas\MemberInfolist;
use App\Filament\Teams\Resources\Members\Tables\MembersTable;
use App\Models\Key;
use App\Models\Lock;
use App\Models\Team;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\ValidationException;
use Livewire\Component as LivewireComponent;

class MemberResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Manage;

    protected static ?int $navigationSort = 3;

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

        return $query->with(['invitation', 'keys.locks.covers'])->whereHas(
            'teams',
            fn (Builder $teams) => $teams->whereKey($team->getKey()),
        );
    }

    public static function form(Schema $schema): Schema
    {
        return MemberForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MemberInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MembersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AccessRelationManager::class,
            KeysRelationManager::class,
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

    public static function viewUrlFor(?User $member, ?Team $team): ?string
    {
        if (! $member instanceof User || ! $team instanceof Team) {
            return null;
        }

        $viewer = auth()->user();

        if (! $viewer instanceof User) {
            return null;
        }

        if (! $member->teams()->whereKey($team->getKey())->exists()) {
            return null;
        }

        $mayLink = $viewer->manages($team)
            || ($viewer->isSystemAdmin() && static::canView($member));

        if (! $mayLink || ! static::canView($member)) {
            return null;
        }

        return static::getUrl('view', ['record' => $member], panel: 'teams', tenant: $team);
    }

    /**
     * Teams member view when available, otherwise Admin user view when the viewer can open it.
     */
    public static function personUrlFor(?User $member, ?Team $team = null): ?string
    {
        if (! $member instanceof User) {
            return null;
        }

        if ($memberUrl = static::viewUrlFor($member, $team)) {
            return $memberUrl;
        }

        $viewer = auth()->user();

        if (! $viewer instanceof User) {
            return null;
        }

        $panel = Filament::getPanel('admin');

        if ($viewer->canAccessPanel($panel) && $viewer->can('view', $member)) {
            return UserResource::getUrl('view', ['record' => $member], panel: $panel->getId());
        }

        return null;
    }

    /**
     * @return EloquentCollection<int, Lock>
     */
    public static function teamLocks(): EloquentCollection
    {
        $team = static::currentTeam();

        if (! $team instanceof Team) {
            return new EloquentCollection;
        }

        return once(fn (): EloquentCollection => Lock::query()->relevantTo($team)->orderBy('name')->get());
    }

    /**
     * @return list<array{name: string, unlocks: list<string>, required_for: list<string>}>
     */
    public static function heldKeyLines(User $member): array
    {
        $team = static::currentTeam();

        if (! $team instanceof Team) {
            return [];
        }

        return $member->keys()
            ->relevantTo($team)
            ->with([
                'locks.covers.requirements.activity',
                'locks.covers.requirements.venue',
                'locks.requirements.activity',
                'locks.requirements.venue',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Key $key): array => [
                'name' => $key->name,
                'unlocks' => $key->lockNames(),
                'required_for' => $key->requirementSummariesFor($team),
            ])
            ->all();
    }

    public static function heldKeysAction(): Action
    {
        return Action::make('heldKeys')
            ->label(__('Keys'))
            ->modalHeading(fn (User $record): string => $record->name)
            ->modalWidth(Width::Large)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'))
            ->visible(fn (?User $record): bool => $record instanceof User
                && $record->namesOfOpenedLocks(static::teamLocks()) !== [])
            ->modalContent(fn (User $record): View => view('filament.members.held-keys', [
                'keys' => static::heldKeyLines($record),
            ]));
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

    public static function invitationActions(?User $record = null, bool $asButton = true): ActionGroup
    {
        $group = ActionGroup::make([
            static::sendInvitationAction($record),
            static::revokeInvitationAction($record),
        ])
            ->record($record)
            ->label(__('Invitation'))
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->visible(fn (User $record): bool => static::canManageInvitation($record));

        return $asButton ? $group->button() : $group;
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
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->modalHeading(fn (User $record): string => match ($record->invitationStatus()) {
                InvitationStatus::Invited, InvitationStatus::Expired => __('Resend invitation'),
                InvitationStatus::Declined => __('Invite again'),
                default => __('Send invitation'),
            })
            ->modalIcon(Heroicon::OutlinedEnvelope)
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
            ->icon(Heroicon::OutlinedXMark)
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
            ->icon(Heroicon::OutlinedUser)
            ->modalHeading(__('Change role'))
            ->modalIcon(Heroicon::OutlinedUser)
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
                RefreshesNavigationChrome::dispatch($livewire);

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
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('danger')
            ->visible(fn (?User $record): bool => static::canManage() && $record instanceof User && ! $record->trashed())
            ->disabled(fn (User $record): bool => static::cannotStepDown($record))
            ->tooltip(fn (User $record): ?string => static::stepDownHint($record))
            ->requiresConfirmation()
            ->action(function (User $record, LivewireComponent $livewire): void {
                static::authorizeManagement();

                try {
                    static::currentTeam()?->recordLeft($record);
                } catch (ValidationException $exception) {
                    static::reportAsNotification($exception);

                    return;
                }

                RefreshesNavigationChrome::dispatch($livewire);
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
