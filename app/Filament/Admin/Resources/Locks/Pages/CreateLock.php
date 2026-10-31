<?php

namespace App\Filament\Admin\Resources\Locks\Pages;

use App\Filament\Admin\Resources\Locks\LockResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLock extends CreateRecord
{
    protected static string $resource = LockResource::class;
}
