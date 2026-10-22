<?php

namespace App\Filament\Member\Resources\KeyRings\Pages;

use App\Filament\Member\Resources\KeyRings\KeyRingResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListKeyRings extends ListRecords
{
    protected static string $resource = KeyRingResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('These are the key rings you hold. A system admin records them.');
    }
}
