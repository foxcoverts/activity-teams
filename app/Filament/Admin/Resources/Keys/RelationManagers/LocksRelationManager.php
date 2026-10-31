<?php

namespace App\Filament\Admin\Resources\Keys\RelationManagers;

use App\Filament\Admin\Resources\Locks\LockResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class LocksRelationManager extends RelationManager
{
    protected static string $relationship = 'locks';

    protected static ?string $relatedResource = LockResource::class;

    protected static ?string $title = 'Unlocks';

    public function table(Table $table): Table
    {
        return $table
            ->searchable(false);
    }
}
