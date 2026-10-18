<?php

namespace App\Filament\Manage\Resources\Members\Pages;

use App\Enums\MembershipRole;
use App\Filament\Manage\Resources\Members\MemberResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Pages\ListRecords;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add')
                ->label('Add a member')
                ->schema([
                    Select::make('user_id')
                        ->label('Person')
                        ->options(function (): array {
                            $team = MemberResource::currentTeam();

                            if ($team === null) {
                                return [];
                            }

                            return User::query()
                                ->whereDoesntHave('teams', fn ($teams) => $teams->whereKey($team->getKey()))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all();
                        })
                        ->required(),
                    ToggleButtons::make('role')
                        ->options(MembershipRole::class)
                        ->default(MembershipRole::Member)
                        ->inline()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $user = User::query()->find($data['user_id']);
                    $role = $data['role'] instanceof MembershipRole
                        ? $data['role']
                        : MembershipRole::from((string) $data['role']);

                    if ($user instanceof User) {
                        MemberResource::currentTeam()?->grant($user, $role);
                    }
                }),
        ];
    }
}
