<?php

namespace App\Filament\Manage\Resources\Members;

use App\Enums\MembershipRole;
use App\Enums\PersonalDataSection as PersonalDataSectionEnum;
use App\Enums\Section as MemberSection;
use App\Filament\Manage\NavigationGroup;
use App\Filament\Manage\Resources\Members\Pages\EditMember;
use App\Filament\Manage\Resources\Members\Pages\ListMembers;
use App\Filament\Manage\Resources\Members\Pages\ViewMember;
use App\Filament\Manage\Resources\Members\RelationManagers\QualificationsRelationManager;
use App\Filament\Manage\Schemas\PersonalDataSection;
use App\Models\Team;
use App\Models\User;
use App\Support\Palette;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class MemberResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Team;

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
            && ($user->manages($team) || $user->hasOversight() || $user->isSystemAdmin());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $team = static::currentTeam();

        if (! $team instanceof Team) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereHas(
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
                        PersonalDataSection::form(PersonalDataSectionEnum::ContactDetails, [
                            TextInput::make('email')
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
                        TextEntry::make('suspended')
                            ->hiddenLabel()
                            ->badge()
                            ->icon(Heroicon::NoSymbol)
                            ->color(Palette::destructive())
                            ->state(__('Suspended'))
                            ->visible(fn (User $record): bool => $record->isSuspended()),
                        PersonalDataSection::make(PersonalDataSectionEnum::ContactDetails, [
                            TextEntry::make('email'),
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
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('changeRole')
                    ->label('Change role')
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
                        $role = $data['role'] instanceof MembershipRole
                            ? $data['role']
                            : MembershipRole::from((string) $data['role']);

                        try {
                            static::currentTeam()?->grant($record, $role);
                        } catch (ValidationException $exception) {
                            static::reportOnRole($exception, $schema);
                        }
                    }),
                Action::make('leave')
                    ->label('Remove from team')
                    ->color('danger')
                    ->disabled(fn (User $record): bool => static::cannotStepDown($record))
                    ->tooltip(fn (User $record): ?string => static::stepDownHint($record))
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        try {
                            static::currentTeam()?->recordLeft($record);
                        } catch (ValidationException $exception) {
                            static::reportAsNotification($exception);
                        }
                    }),
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
        $role = $schema->getFlatComponents(withHidden: true)['role'] ?? null;
        $path = $role instanceof Component ? $role->getStatePath() : 'role';

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

    public static function validationMessage(ValidationException $exception): string
    {
        $message = collect($exception->errors())->flatten()->first();

        return is_string($message) ? $message : __('Appoint another manager before this one steps down.');
    }
}
