<?php

namespace App\Filament\Manage\Resources\Members\Pages;

use App\Filament\Manage\Resources\Members\MemberResource;
use Filament\Resources\Pages\EditRecord;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;
}
