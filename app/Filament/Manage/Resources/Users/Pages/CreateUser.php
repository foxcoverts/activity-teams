<?php

namespace App\Filament\Manage\Resources\Users\Pages;

use App\Filament\Manage\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['password'] ?? null)) {
            $data['password'] = null;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->record;
        if (! $user->isActive()) {
            $user->sendAccountSetupNotification();
        }
    }
}
