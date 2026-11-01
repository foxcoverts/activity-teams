<?php

namespace App\Filament\Member\Resources\Inbox\Pages;

use App\Filament\Member\Resources\Inbox\InboxResource;
use Filament\Resources\Pages\ListRecords;

class ListNotifications extends ListRecords
{
    protected static string $resource = InboxResource::class;
}
