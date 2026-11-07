<?php

namespace App\Filament\Admin\Resources\Users\RelationManagers;

use App\Enums\MembershipRole;
use App\Filament\Admin\Resources\Members\MemberResource;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class MembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    protected static ?string $title = 'Teams';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('team_avatar')
                    ->label('')
                    ->state(fn (TeamMembership $record): ?string => $record->team?->getFilamentAvatarUrl())
                    ->alt(fn (TeamMembership $record): string => $record->team?->name ?? '')
                    ->imageSize(32)
                    ->square()
                    ->width('1%')
                    ->grow(false),
                TextColumn::make('team.name')
                    ->label('Team'),
                TextColumn::make('role')
                    ->badge(),
                TextColumn::make('left_at')
                    ->dateTime()
                    ->placeholder('Current'),
            ])
            ->headerActions([
                Action::make('grant')
                    ->label('Add to a team')
                    ->visible(fn (): bool => ($owner = $this->getOwnerRecord()) instanceof User && ! $owner->trashed())
                    ->schema([
                        Select::make('team_id')
                            ->label('Team')
                            ->options(fn (): array => Team::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->required(),
                        ToggleButtons::make('role')
                            ->options(MembershipRole::class)
                            ->default(MembershipRole::Member)
                            ->inline()
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $owner = $this->getOwnerRecord();

                        if (! $owner instanceof User) {
                            return;
                        }

                        $team = Team::query()->findOrFail($data['team_id']);
                        $role = $data['role'] instanceof MembershipRole
                            ? $data['role']
                            : MembershipRole::from((string) $data['role']);

                        $team->grant($owner, $role);
                    }),
            ])
            ->recordActions([
                Action::make('changeRole')
                    ->label('Change role')
                    ->visible(fn (TeamMembership $record): bool => $record->left_at === null && ($owner = $this->getOwnerRecord()) instanceof User && ! $owner->trashed())
                    ->disabled(fn (TeamMembership $record): bool => static::cannotStepDown($record))
                    ->tooltip(fn (TeamMembership $record): ?string => static::stepDownHint($record))
                    ->fillForm(fn (TeamMembership $record): array => [
                        'role' => $record->role->value,
                    ])
                    ->schema([
                        ToggleButtons::make('role')
                            ->options(MembershipRole::class)
                            ->inline()
                            ->required(),
                    ])
                    ->action(function (TeamMembership $record, array $data, Schema $schema): void {
                        $record->loadMissing('team', 'user');
                        $role = $data['role'] instanceof MembershipRole
                            ? $data['role']
                            : MembershipRole::from((string) $data['role']);

                        try {
                            $record->team->grant($record->user, $role);
                        } catch (ValidationException $exception) {
                            MemberResource::reportOnRole($exception, $schema);
                        }
                    }),
                Action::make('leave')
                    ->label('Leave team')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (TeamMembership $record): bool => $record->left_at === null && ($owner = $this->getOwnerRecord()) instanceof User && ! $owner->trashed())
                    ->disabled(fn (TeamMembership $record): bool => static::cannotStepDown($record))
                    ->tooltip(fn (TeamMembership $record): ?string => static::stepDownHint($record))
                    ->action(function (TeamMembership $record): void {
                        $record->loadMissing('team', 'user');

                        try {
                            $record->team->recordLeft($record->user);
                        } catch (ValidationException $exception) {
                            MemberResource::reportAsNotification($exception);
                        }
                    }),
            ]);
    }

    protected static function cannotStepDown(TeamMembership $membership): bool
    {
        if ($membership->left_at !== null) {
            return false;
        }

        $membership->loadMissing('team', 'user');

        if (! $membership->user instanceof User) {
            return false;
        }

        return $membership->team->isOnlyManager($membership->user);
    }

    protected static function stepDownHint(TeamMembership $membership): ?string
    {
        return static::cannotStepDown($membership) ? Team::lastManagerMessage() : null;
    }
}
