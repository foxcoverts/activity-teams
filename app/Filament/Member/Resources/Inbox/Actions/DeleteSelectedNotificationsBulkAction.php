<?php

namespace App\Filament\Member\Resources\Inbox\Actions;

use App\Filament\Concerns\HasStableLivewireKey;
use Filament\Actions\DeleteBulkAction;

class DeleteSelectedNotificationsBulkAction extends DeleteBulkAction
{
    use HasStableLivewireKey;
}
