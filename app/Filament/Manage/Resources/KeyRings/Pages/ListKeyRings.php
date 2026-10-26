<?php

namespace App\Filament\Manage\Resources\KeyRings\Pages;

use App\Filament\Manage\Resources\KeyRings\KeyRingResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListKeyRings extends ListRecords
{
    protected static string $resource = KeyRingResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('Key rings that open a key this team needs. Pass one to a member, or back to the cupboard.');
    }
}
