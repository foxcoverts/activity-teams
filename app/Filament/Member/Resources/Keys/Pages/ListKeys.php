<?php

namespace App\Filament\Member\Resources\Keys\Pages;

use App\Filament\Member\Resources\Keys\KeyResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListKeys extends ListRecords
{
    protected static string $resource = KeyResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('These are the keys you hold. You can pass one to another member, or back to the cupboard.');
    }
}
