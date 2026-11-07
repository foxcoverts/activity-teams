<?php

namespace App\Filament\Teams\Resources\Members\Tables;

use App\Enums\AccountStatus;
use App\Enums\MembershipRole;
use App\Filament\Teams\Resources\Members\MemberResource;
use App\Models\Lock;
use App\Models\User;
use App\Support\Palette;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('avatar')
                    ->label('')
                    ->state(fn (User $record): ?string => $record->getFilamentAvatarUrl())
                    ->alt(fn (User $record): string => $record->name)
                    ->imageSize(32)
                    ->circular()
                    ->width('1%')
                    ->grow(false),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('membership_role')
                    ->label('Role')
                    ->badge()
                    ->state(fn (User $record): ?MembershipRole => MemberResource::roleOnCurrentTeam($record)),
                TextColumn::make('status')
                    ->state(fn (User $record): AccountStatus|string => $record->status())
                    ->badge(),
                TextColumn::make('keys')
                    ->label(__('Keys'))
                    ->badge()
                    ->icon(Heroicon::OutlinedKey)
                    ->state(fn (User $record): ?string => $record->namesOfOpenedLocks(MemberResource::teamLocks()) === []
                        ? null
                        : __('Key holder'))
                    ->color(fn (?string $state): array => filled($state) ? Palette::positive() : Palette::neutral())
                    ->action(MemberResource::heldKeysAction())
                    ->disabledClick(fn (?string $state): bool => blank($state))
                    ->visible(fn (): bool => MemberResource::teamLocks()->isNotEmpty()),
            ])
            ->filters([
                Filter::make('not_accepted')
                    ->label('Not yet accepted')
                    ->query(fn (Builder $query): Builder => $query->whereNull('users.email')->whereNull('users.deleted_at')),
                SelectFilter::make('key')
                    ->label(__('Locks'))
                    ->options(fn (): array => MemberResource::teamLocks()->pluck('name', 'id')->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $keyId = $data['value'] ?? null;

                        if (blank($keyId)) {
                            return $query;
                        }

                        $key = Lock::query()->find($keyId);

                        if (! $key instanceof Lock) {
                            return $query;
                        }

                        return $query->holding($key);
                    })
                    ->visible(fn (): bool => MemberResource::teamLocks()->isNotEmpty()),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    MemberResource::invitationActions(asButton: false),
                    MemberResource::changeRoleAction(),
                    MemberResource::removeFromTeamAction(),
                ]),
            ]);
    }
}
