<?php

namespace App\Filament\Manage\Resources\Keys\Pages;

use App\Filament\Manage\Resources\Keys\KeyResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListKeys extends ListRecords
{
    protected static string $resource = KeyResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('Keys required for this team\'s activities at its venues, and every ring that holds them.');
    }
}
