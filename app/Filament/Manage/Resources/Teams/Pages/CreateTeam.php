<?php

namespace App\Filament\Manage\Resources\Teams\Pages;

use App\Enums\MembershipRole;
use App\Filament\Manage\Resources\Teams\TeamResource;
use App\Models\Team;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateTeam extends CreateRecord
{
    protected static string $resource = TeamResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->managerId = isset($data['manager_id']) ? (string) $data['manager_id'] : $this->managerId;
        unset($data['manager_id']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $managerId = $this->managerId ?? (string) ($this->data['manager_id'] ?? '');
        $manager = User::query()->find($managerId);
        $team = $this->getRecord();

        if ($manager instanceof User && $team instanceof Team) {
            $team->grant($manager, MembershipRole::Manager);
        }
    }

    public ?string $managerId = null;
}
