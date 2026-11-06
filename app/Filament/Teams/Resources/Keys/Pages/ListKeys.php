<?php

namespace App\Filament\Teams\Resources\Keys\Pages;

use App\Filament\Teams\Resources\Keys\KeyResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListKeys extends ListRecords
{
    protected static string $resource = KeyResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('Keys that open a lock this team needs. Pass one to a member, or back to the cupboard.');
    }
}
