<?php

namespace App\Filament\Teams\Resources\MerchandiseOrders\Tables;

use App\Enums\MerchandiseOrderStatus;
use App\Filament\Support\Tables\PersonColumn;
use App\Models\MerchandiseOrder;
use App\Models\Team;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class MerchandiseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('submitted_at')
                    ->label(__('Submitted'))
                    ->dateTime('j M Y H:i')
                    ->sortable(),
                PersonColumn::make('user.name')
                    ->label(__('Member'))
                    ->memberUrl()
                    ->visible(fn (): bool => self::viewerManagesTenant())
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
                TextColumn::make('total')
                    ->label(__('Total'))
                    ->state(fn (MerchandiseOrder $record): string => Number::currency($record->total(), 'GBP', 'en_GB')),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(MerchandiseOrderStatus::class)
                    ->visible(fn (): bool => self::viewerManagesTenant()),
                SelectFilter::make('user_id')
                    ->label(__('Member'))
                    ->options(fn (): array => self::memberFilterOptions())
                    ->default('me')
                    ->placeholder(__('All'))
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        if ($value === 'me') {
                            $user = auth()->user();

                            return $user instanceof User
                                ? $query->whereBelongsTo($user)
                                : $query->whereRaw('0 = 1');
                        }

                        return $query->where('user_id', $value);
                    })
                    ->visible(fn (): bool => self::viewerManagesTenant()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('submitted_at', 'desc');
    }

    /**
     * @return array<string, string|array<string, string>>
     */
    protected static function memberFilterOptions(): array
    {
        $team = Filament::getTenant();
        $user = auth()->user();

        if (! $team instanceof Team || ! $user instanceof User) {
            return [
                'me' => __('Me'),
            ];
        }

        $members = User::query()
            ->whereHas(
                'teams',
                fn (Builder $teams): Builder => $teams->whereKey($team->getKey()),
            )
            ->whereKeyNot($user->getKey())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return [
            'me' => __('Me'),
            __('Members') => $members,
        ];
    }

    protected static function viewerManagesTenant(): bool
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Team && (bool) auth()->user()?->manages($tenant);
    }
}
