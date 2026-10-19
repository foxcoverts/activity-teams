<?php

namespace App\Filament\Manage\Resources\TeamKeys\Pages;

use App\Filament\Manage\Resources\TeamKeys\TeamKeyResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListTeamKeys extends ListRecords
{
    protected static string $resource = TeamKeyResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('Keys required for this team\'s activities at its venues, and every ring that holds them.');
    }
}
