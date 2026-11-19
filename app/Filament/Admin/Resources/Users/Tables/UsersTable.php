<?php

namespace App\Filament\Admin\Resources\Users\Tables;

use App\Actions\DeleteUserAction;
use App\Enums\AccountRole;
use App\Enums\AccountStatus;
use App\Enums\MembershipRole;
use App\Filament\Admin\Resources\Users\UserInvitationActions;
use App\Filament\Support\Tables\PersonColumn;
use App\Models\User;
use App\Support\Palette;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Throwable;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(function (Builder $query, HasTable $livewire): Builder {
                $teamId = static::filteredTeamId($livewire);

                if ($teamId === null) {
                    return $query->with('teams');
                }

                return $query->with([
                    'teams' => fn (BelongsToMany $teams): BelongsToMany => $teams->whereKey($teamId),
                ]);
            })
            ->columns([
                PersonColumn::make('name')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (User $record): AccountStatus => $record->status()),
                TextColumn::make('roles')
                    ->badge()
                    ->state(fn (User $record): array => static::roleBadges($record)),
                TextColumn::make('teams.name')
                    ->label(__('Teams'))
                    ->visible(fn (HasTable $livewire): bool => blank($livewire->getTableFilterState('team')['value'] ?? null))
                    ->badge()
                    ->color(function (?string $state, User $record): array {
                        if ($state === null) {
                            return Palette::neutral();
                        }

                        return $record->teams
                            ->firstWhere('name', $state)
                            ?->brandColor()
                            ->getColor() ?? Palette::neutral();
                    }),
                TextColumn::make('team_role')
                    ->label(__('Team role'))
                    ->badge()
                    ->hidden(fn (HasTable $livewire): bool => static::filteredTeamId($livewire) === null)
                    ->state(function (User $record, HasTable $livewire): ?MembershipRole {
                        $teamId = static::filteredTeamId($livewire);

                        return $teamId === null ? null : $record->roleOn($teamId);
                    }),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label(__('Roles'))
                    ->multiple()
                    ->options([
                        '__empty' => __('None'),
                        __('Team') => [
                            MembershipRole::Manager->value => MembershipRole::Manager->getLabel(),
                            MembershipRole::Member->value => MembershipRole::Member->getLabel(),
                            MembershipRole::Parent->value => MembershipRole::Parent->getLabel(),
                        ],
                        __('System') => [
                            AccountRole::Oversight->value => AccountRole::Oversight->getLabel(),
                            AccountRole::SystemAdmin->value => AccountRole::SystemAdmin->getLabel(),
                        ],
                    ])
                    ->query(fn (Builder $query, array $data): Builder => static::applyRolesFilter($query, $data)),
                SelectFilter::make('team')
                    ->relationship('teams', 'name', hasEmptyOption: true),
                Filter::make('not_accepted')
                    ->label(__('Not yet accepted'))
                    ->query(fn (Builder $query): Builder => $query->whereNull('users.email')->whereNull('users.deleted_at')),
            ])
            ->recordActions([
                UserInvitationActions::group(),
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

    /**
     * @return list<MembershipRole|AccountRole>
     */
    protected static function roleBadges(User $user): array
    {
        return array_values(array_filter([$user->teamRole(), ...$user->roles()]));
    }

    private static function filteredTeamId(HasTable $livewire): ?string
    {
        $value = $livewire->getTableFilterState('team')['value'] ?? null;

        if (! is_string($value) || $value === '' || $value === '__empty') {
            return null;
        }

        return $value;
    }

    /**
     * @param  Builder<User>  $query
     * @param  array{values?: list<string>|null}  $data
     * @return Builder<User>
     */
    private static function applyRolesFilter(Builder $query, array $data): Builder
    {
        $values = $data['values'] ?? [];

        if (! is_array($values) || $values === []) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($values): void {
            /** @var list<string> $membershipRoles */
            $membershipRoles = [];

            foreach ($values as $value) {
                if (! is_string($value)) {
                    continue;
                }

                if ($value === '__empty') {
                    $query->orWhere(function (Builder $query): void {
                        $query
                            ->where('is_system_admin', false)
                            ->where('oversight', false)
                            ->whereDoesntHave('teams');
                    });

                    continue;
                }

                $accountRole = AccountRole::tryFrom($value);

                if ($accountRole instanceof AccountRole) {
                    match ($accountRole) {
                        AccountRole::Oversight => $query->orWhere('oversight', true),
                        AccountRole::SystemAdmin => $query->orWhere('is_system_admin', true),
                    };

                    continue;
                }

                if (MembershipRole::tryFrom($value) instanceof MembershipRole) {
                    $membershipRoles[] = $value;
                }
            }

            if ($membershipRoles !== []) {
                $query->orWhereHas(
                    'teams',
                    fn (Builder $query): Builder => $query->whereIn('role', $membershipRoles),
                );
            }
        });
    }
}
