<?php

namespace App\Policies;

use App\Models\NotificationSetting;
use App\Models\User;

class NotificationSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isSuspended();
    }

    public function view(User $user, NotificationSetting $setting): bool
    {
        return $this->owns($user, $setting);
    }

    public function update(User $user, NotificationSetting $setting): bool
    {
        return $this->owns($user, $setting);
    }

    public function delete(User $user, NotificationSetting $setting): bool
    {
        return $this->owns($user, $setting);
    }

    private function owns(User $user, NotificationSetting $setting): bool
    {
        return ! $user->isSuspended()
            && (string) $user->getKey() === (string) $setting->user_id;
    }
}
