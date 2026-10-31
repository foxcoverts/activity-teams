<?php

namespace App\Filament\Admin\Resources\InboundMails\Pages;

use App\Filament\Admin\Resources\InboundMails\InboundMailResource;
use Filament\Resources\Pages\ListRecords;

class ListInboundMails extends ListRecords
{
    protected static string $resource = InboundMailResource::class;
}
