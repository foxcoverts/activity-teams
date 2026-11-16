<?php

namespace App\Filament\Admin\Widgets;

use App\Models\User;

class AdminAllCaughtUpWidget extends AdminSection
{
    protected static ?int $sort = 2;

    protected string $view = 'filament.admin.widgets.all-caught-up';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ($user->isSystemAdmin() || $user->hasOversight());
    }

    protected function sectionData(): array
    {
        return [
            'caughtUp' => $this->attention()->isCaughtUp(),
        ];
    }
}
